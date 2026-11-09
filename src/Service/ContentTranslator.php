<?php

namespace App\Service;

use App\Entity\ContentTranslation;
use App\Entity\Discussion;
use App\Entity\Proposition;
use App\Entity\Revendication;
use App\Entity\ServiceOffer;
use App\Repository\ContentTranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Traduction du contenu des utilisateurs : automatique (MyMemory gratuit par défaut, LibreTranslate ou DeepL en option) ou proposée par un contributeur. */
class ContentTranslator
{
    /** type => [classe, champs traduisibles] */
    public const TYPES = [
        'revendication' => [Revendication::class, ['title', 'summary', 'verificationNote']],
        'proposition' => [Proposition::class, ['title', 'description']],
        'discussion' => [Discussion::class, ['title', 'body']],
        'service_offer' => [ServiceOffer::class, ['title', 'description']],
    ];

    /** Codes DeepL par langue du site. Les langues absentes restent ouvertes aux contributeurs. */
    private const DEEPL = ['fr' => 'FR', 'en' => 'EN-GB', 'es' => 'ES', 'de' => 'DE', 'it' => 'IT', 'pt' => 'PT-PT', 'ar' => 'AR', 'tr' => 'TR', 'ru' => 'RU', 'zh' => 'ZH-HANS', 'ja' => 'JA'];

    /** Langues du site sans code DeepL (hindi) : traitées par MyMemory ou LibreTranslate. */
    private const OTHER = ['hi'];

    /** @var array<string,?ContentTranslation> */
    private array $cache = [];

    /** @var array<string,array{object,string}> contenus lus dans une langue sans traduction : traduits après l'envoi de la page */
    private array $queue = [];

    public function __construct(
        private readonly ContentTranslationRepository $repo,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $http,
        #[Autowire('%env(DEEPL_API_KEY)%')] private readonly string $apiKey,
        #[Autowire('%env(DEEPL_API_URL)%')] private readonly string $apiUrl,
        #[Autowire('%env(TRANSLATE_PROVIDER)%')] private readonly string $providerSetting = '',
        #[Autowire('%env(MYMEMORY_EMAIL)%')] private readonly string $myMemoryEmail = '',
        #[Autowire('%env(LIBRETRANSLATE_URL)%')] private readonly string $libreUrl = '',
        #[Autowire('%env(LIBRETRANSLATE_KEY)%')] private readonly string $libreKey = '',
    ) {
    }

    public static function typeOf(object $e): ?string
    {
        foreach (self::TYPES as $t => [$class]) {
            if ($e instanceof $class) {
                return $t;
            }
        }

        return null;
    }

    public function sourceLocale(object $e): ?string
    {
        return method_exists($e, 'getLocale') ? $e->getLocale() : null;
    }

    /** Fournisseur actif : deepl (payant ou palier gratuit avec clé), libretranslate (libre, auto-hébergeable), mymemory (gratuit, sans clé), none. */
    public function provider(): string
    {
        $p = strtolower(trim($this->providerSetting));
        if (in_array($p, ['deepl', 'libretranslate', 'mymemory', 'none'], true)) {
            return $p;
        }
        if ('' !== trim($this->apiKey)) {
            return 'deepl';
        }

        return '' !== trim($this->libreUrl) ? 'libretranslate' : 'mymemory';
    }

    public function enabled(): bool
    {
        return 'none' !== $this->provider();
    }

    /** Traduit une liste de textes. Lève une RuntimeException de code 429 si le quota gratuit du jour est épuisé. */
    private function translateTexts(array $texts, string $locale, ?string $src): array
    {
        return match ($this->provider() === 'deepl' && !isset(self::DEEPL[$locale]) ? 'mymemory' : $this->provider()) {
            'deepl' => $this->viaDeepl($texts, $locale, $src),
            'libretranslate' => array_map(fn ($t) => $this->viaLibre($t, $locale, $src), $texts),
            default => array_map(fn ($t) => $this->viaMyMemory($t, $locale, $src), $texts),
        };
    }

    /** Traduit des textes d'interface (utilisé par app:traductions:completer). Quota gratuit : code 429. */
    public function translateUi(array $texts, string $locale, string $src = 'fr'): array
    {
        return $this->translateTexts($texts, $locale, $src);
    }

    private function viaDeepl(array $texts, string $locale, ?string $src): array
    {
        $body = ['text' => $texts, 'target_lang' => self::DEEPL[$locale], 'preserve_formatting' => true];
        if ($src && isset(self::DEEPL[$src])) {
            $body['source_lang'] = substr(self::DEEPL[$src], 0, 2);
        }
        $res = $this->http->request('POST', rtrim($this->apiUrl, '/') . '/v2/translate', [
            'headers' => ['Authorization' => 'DeepL-Auth-Key ' . $this->apiKey],
            'json' => $body,
            'timeout' => 20,
        ])->toArray();
        $out = [];
        foreach ($texts as $i => $_) {
            $out[] = (string) ($res['translations'][$i]['text'] ?? '');
        }

        return $out;
    }

    private function viaLibre(string $text, string $locale, ?string $src): string
    {
        $body = ['q' => $text, 'source' => ($src && (isset(self::DEEPL[$src]) || in_array($src, self::OTHER, true))) ? $src : 'auto', 'target' => $locale, 'format' => 'text'];
        if ('' !== $this->libreKey) {
            $body['api_key'] = $this->libreKey;
        }
        $res = $this->http->request('POST', rtrim($this->libreUrl, '/') . '/translate', ['json' => $body, 'timeout' => 30])->toArray(false);
        if (!isset($res['translatedText'])) {
            throw new \RuntimeException('LibreTranslate : ' . ($res['error'] ?? 'réponse invalide'), str_contains((string) ($res['error'] ?? ''), 'limit') ? 429 : 0);
        }

        return (string) $res['translatedText'];
    }

    /** MyMemory : gratuit, sans clé, 500 caractères par requête, quota quotidien (augmenté en déclarant MYMEMORY_EMAIL). */
    private function viaMyMemory(string $text, string $locale, ?string $src): string
    {
        $map = ['zh' => 'zh-CN', 'pt' => 'pt-PT'];
        $from = $map[$src ?: 'fr'] ?? ($src ?: 'fr');
        $to = $map[$locale] ?? $locale;
        $parts = [];
        foreach (preg_split('/(?<=[.!?\n])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $sentence) {
            $last = count($parts) - 1;
            if ($last >= 0 && mb_strlen($parts[$last] . ' ' . $sentence) <= 450) {
                $parts[$last] .= ' ' . $sentence;
            } else {
                foreach (mb_str_split($sentence, 450) as $chunk) {
                    $parts[] = $chunk;
                }
            }
        }
        $out = [];
        foreach ($parts as $chunk) {
            $q = ['q' => $chunk, 'langpair' => $from . '|' . $to];
            if ('' !== $this->myMemoryEmail) {
                $q['de'] = $this->myMemoryEmail;
            }
            $res = $this->http->request('GET', 'https://api.mymemory.translated.net/get', ['query' => $q, 'timeout' => 20])->toArray(false);
            $status = (int) ($res['responseStatus'] ?? 0);
            if (!empty($res['quotaFinished']) || 429 === $status) {
                throw new \RuntimeException("Quota gratuit MyMemory épuisé pour aujourd'hui : reprise demain.", 429);
            }
            $t = (string) ($res['responseData']['translatedText'] ?? '');
            if (200 !== $status || '' === $t || str_contains($t, 'MYMEMORY WARNING')) {
                throw new \RuntimeException('MyMemory : réponse refusée (' . $status . ').');
            }
            $out[] = html_entity_decode($t, ENT_QUOTES | ENT_HTML5);
        }

        return implode(' ', $out);
    }

    /** @return array<string,array{object,string}> */
    public function drainQueue(): array
    {
        $q = $this->queue;
        $this->queue = [];

        return $q;
    }

    public function supports(string $locale): bool
    {
        return isset(self::DEEPL[$locale]) || in_array($locale, self::OTHER, true);
    }

    /** Texte à afficher pour ce champ dans la langue du lecteur (traduction publiée, sinon l'original). */
    public function text(object $e, string $field, string $locale): string
    {
        $getter = 'get' . ucfirst($field);
        $original = (string) $e->$getter();
        $t = $this->translation($e, $locale);
        if (!$t && $this->enabled() && $this->supports($locale) && $locale !== ($this->sourceLocale($e) ?? 'fr') && self::typeOf($e) && $e->getId()) {
            $this->queue[self::typeOf($e) . '|' . $e->getId() . '|' . $locale] = [$e, $locale];
        }

        return $t && isset($t->getFields()[$field]) && '' !== $t->getFields()[$field] ? $t->getFields()[$field] : $original;
    }

    public function translation(object $e, string $locale): ?ContentTranslation
    {
        $type = self::typeOf($e);
        if (!$type || !method_exists($e, 'getId') || !$e->getId() || $locale === ($this->sourceLocale($e) ?? 'fr')) {
            return null;
        }
        $k = $type . '|' . $e->getId() . '|' . $locale;
        if (!array_key_exists($k, $this->cache)) {
            $this->cache[$k] = $this->repo->findPublished($type, (int) $e->getId(), $locale);
        }

        return $this->cache[$k];
    }

    /** Traduit l'entité vers $locale (fournisseur gratuit par défaut). Retourne false si non traduisible ou désactivé. */
    public function autoTranslate(object $e, string $locale): bool
    {
        $type = self::typeOf($e);
        if (!$type || !$this->enabled() || !$this->supports($locale) || $locale === ($this->sourceLocale($e) ?? '')) {
            return false;
        }
        $fields = [];
        $texts = [];
        foreach (self::TYPES[$type][1] as $f) {
            $g = 'get' . ucfirst($f);
            $v = trim((string) $e->$g());
            if ('' !== $v) {
                $fields[] = $f;
                $texts[] = $v;
            }
        }
        if (!$texts) {
            return false;
        }
        $translated = $this->translateTexts($texts, $locale, $this->sourceLocale($e));
        $out = [];
        foreach ($fields as $i => $f) {
            $out[$f] = (string) ($translated[$i] ?? '');
        }
        $this->em->persist(new ContentTranslation($type, (int) $e->getId(), $locale, $out, ContentTranslation::SRC_AUTO, ContentTranslation::PUBLISHED));
        $this->em->flush();

        return true;
    }
}
