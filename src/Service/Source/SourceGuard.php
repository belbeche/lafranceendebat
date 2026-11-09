<?php

namespace App\Service\Source;

use App\Service\DebatObligations;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * CONTROLE D'UNE SOURCE AVANT TOUT REMPLISSAGE. Rien n'est envoye a l'IA tant que ces controles n'ont pas eu lieu.
 *
 *  1. adresse en https, sur un domaine reconnu : source officielle (DebatObligations::OFFICIAL) ou presse d'information reconnue ;
 *  2. page accessible (HTTP 200, HTML), avec un titre ;
 *  3. date de publication et media identifies ;
 *  4. DROITS : une page qui reserve la fouille de textes (meta "tdm-reservation"), demande "noai", ou dont la suite est
 *     payante, n'est jamais lue en entier. Seules ses metadonnees publiques (titre, chapo, date, auteur), faites pour etre
 *     partagees, servent a preparer le brouillon. Le texte integral n'est ni copie, ni envoye a l'IA.
 *
 * Mode resultant : "full" (texte lisible), "meta" (metadonnees seules), "refused" (source non admise).
 */
class SourceGuard
{
    /** Presse d'information generale reconnue (liste volontairement courte, a etendre par l'equipe). */
    public const PRESS = ['lemonde.fr', 'lefigaro.fr', 'lexpress.fr', 'liberation.fr', 'lepoint.fr', 'nouvelobs.com', 'francetvinfo.fr', 'la-croix.com', 'ouest-france.fr', 'lesechos.fr', 'latribune.fr', 'courrierinternational.com', 'alternatives-economiques.fr', 'theconversation.com', 'afp.com', 'reuters.com', 'apnews.com', 'bbc.com', 'bbc.co.uk'];
    private const MAX_BODY = 6000;
    private const PAYWALL = ['réservée à nos abonnés', 'reservee a nos abonnes', 'réservé aux abonnés', 'article réservé', 'abonnez-vous pour', 'continuez votre lecture', 'subscribe to continue', 'subscribers only'];

    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    /** @return array{ok:bool,mode:string,url:string,checks:list<array{label:string,ok:bool,detail:string}>,meta:array<string,string>,body:string} */
    public function inspect(string $url, ?string $html = null): array
    {
        $url = trim($url);
        $report = ['ok' => false, 'mode' => 'refused', 'url' => $url, 'checks' => [], 'meta' => [], 'body' => ''];
        $add = static function (array &$r, string $label, bool $ok, string $detail) { $r['checks'][] = ['label' => $label, 'ok' => $ok, 'detail' => $detail]; };

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $https = (bool) preg_match('~^https://[^\s/]+\.[^\s/]+~i', $url) && false === strpos($url, '@');
        $add($report, 'Adresse en https', $https, $https ? $host : 'Seules les adresses https:// sont acceptées.');
        $kind = $this->kind($host);
        $add($report, 'Domaine reconnu', null !== $kind, null !== $kind ? ($kind === 'officielle' ? 'Source officielle' : 'Presse d\'information reconnue') . ' (' . $host . ')' : 'Ce domaine n\'est ni une source officielle ni un média de la liste. Ajoutez la source à la main dans « Faits vérifiés ».');
        if (!$https || null === $kind) {
            return $report;
        }
        if (null === $html) {
            try {
                $res = $this->http?->request('GET', $url, ['timeout' => 15, 'max_redirects' => 3, 'headers' => ['Accept' => 'text/html', 'User-Agent' => 'LaFranceEnDebat-verification-source/1.0 (+https://lafranceendebat.fr)']]);
                $code = $res?->getStatusCode() ?? 0;
                $final = strtolower((string) parse_url((string) $res?->getInfo('url'), PHP_URL_HOST));
                $okType = str_contains(strtolower($res?->getHeaders(false)['content-type'][0] ?? ''), 'html');
                if ($final !== $host && null === $this->kind($final)) {
                    $add($report, 'Redirection sûre', false, 'La page renvoie vers un domaine non reconnu : ' . $final);

                    return $report;
                }
                $html = (200 === $code && $okType) ? mb_substr($res->getContent(false), 0, 600000) : null;
                $add($report, 'Page accessible', null !== $html, null !== $html ? 'HTTP 200' : 'HTTP ' . $code . ' ou contenu non HTML');
            } catch (\Throwable $e) {
                $add($report, 'Page accessible', false, 'Lecture impossible : ' . mb_substr($e->getMessage(), 0, 120));

                return $report;
            }
            if (null === $html) {
                return $report;
            }
        }
        $meta = self::meta($html);
        $report['meta'] = $meta;
        $add($report, 'Titre trouvé', '' !== ($meta['title'] ?? ''), $meta['title'] ?? 'aucun');
        $add($report, 'Média et date de publication', '' !== ($meta['site'] ?? '') && '' !== ($meta['published'] ?? ''), trim(($meta['site'] ?? '?') . ', ' . ($meta['published'] ?? 'date inconnue')));

        $tdm = self::tdmReserved($html);
        $paywall = self::paywalled($html);
        $add($report, 'Fouille de textes autorisée', !$tdm, $tdm ? 'La page réserve la fouille de textes et la lecture par IA (tdm-reservation) : le texte intégral ne sera pas lu.' : 'Aucune réserve déclarée');
        $add($report, 'Texte intégral accessible', !$paywall, $paywall ? 'Suite réservée aux abonnés : seule la présentation publique est utilisable.' : 'Texte en accès libre');

        $report['ok'] = '' !== ($meta['title'] ?? '');
        $report['mode'] = !$report['ok'] ? 'refused' : (($tdm || $paywall) ? 'meta' : 'full');
        if ('full' === $report['mode']) {
            $report['body'] = self::bodyText($html);
            if (mb_strlen($report['body']) < 400) {
                $report['mode'] = 'meta';
                $report['body'] = '';
            }
        }

        return $report;
    }

    public function kind(string $host): ?string
    {
        foreach (DebatObligations::OFFICIAL as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) {
                return 'officielle';
            }
        }
        foreach (self::PRESS as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) {
                return 'presse';
            }
        }

        return null;
    }

    /** @return array<string,string> */
    public static function meta(string $html): array
    {
        $get = static function (string $key) use ($html): string {
            foreach (['property', 'name'] as $attr) {
                if (preg_match('~<meta[^>]+' . $attr . '=["\']' . preg_quote($key, '~') . '["\'][^>]*content=["\']([^"\']*)["\']~i', $html, $m) || preg_match('~<meta[^>]+content=["\']([^"\']*)["\'][^>]*' . $attr . '=["\']' . preg_quote($key, '~') . '["\']~i', $html, $m)) {
                    return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
            }

            return '';
        };
        $title = $get('og:title') ?: (preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m) ? trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '');

        return [
            'title' => mb_substr($title, 0, 250),
            'description' => mb_substr($get('og:description') ?: $get('description'), 0, 600),
            'site' => mb_substr($get('og:site_name'), 0, 80),
            'published' => mb_substr($get('article:published_time') ?: $get('publishedTime'), 0, 10),
            'author' => mb_substr($get('author'), 0, 120),
        ];
    }

    public static function tdmReserved(string $html): bool
    {
        return (bool) (preg_match('~<meta[^>]+name=["\']tdm-reservation["\'][^>]*content=["\']1["\']~i', $html) || preg_match('~<meta[^>]+content=["\']1["\'][^>]*name=["\']tdm-reservation["\']~i', $html)
            || preg_match('~<meta[^>]+name=["\'](?:robots|googlebot)["\'][^>]*content=["\'][^"\']*noai~i', $html));
    }

    public static function paywalled(string $html): bool
    {
        if (preg_match('~"isAccessibleForFree"\s*:\s*(false|"False"|"false")~i', $html)) {
            return true;
        }
        $low = mb_strtolower(strip_tags($html));
        foreach (self::PAYWALL as $m) {
            if (str_contains($low, $m)) {
                return true;
            }
        }

        return false;
    }

    private static function bodyText(string $html): string
    {
        $html = preg_replace('~<(script|style|nav|header|footer|aside|form|noscript)[^>]*>.*?</\1>~is', ' ', $html);
        if (preg_match('~<article[^>]*>(.*?)</article>~is', $html, $m)) {
            $html = $m[1];
        }
        $text = html_entity_decode(strip_tags(preg_replace('~</(p|h\d|li|div)>~i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_substr(trim(preg_replace("/[ \t]+/u", ' ', preg_replace("/\n{2,}/", "\n", $text))), 0, self::MAX_BODY);
    }
}
