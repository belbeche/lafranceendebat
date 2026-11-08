<?php

namespace App\Service;

/**
 * Filtre de moderation a priori de La France en Debat.
 *
 * Objectif : bloquer les insultes, menaces, appels a la violence et divulgations de
 * donnees personnelles AVANT publication, sans jamais juger une opinion.
 * Un desaccord, meme vif, n'est pas une insulte.
 */
class ContentModerator
{
    public const REASON_INSULT = 'insulte';
    public const REASON_THREAT = 'menace';
    public const REASON_PERSONAL_DATA = 'donnees_personnelles';
    public const REASON_HATE = 'haine';

    private const LEET = ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's'];

    /** Motifs de menace ou d'appel a la violence (appliques au texte normalise). */
    private const THREAT_PATTERNS = [
        '/\bje (vais|va|veux) (te|vous|le|la|les) (tuer|buter|massacrer|fracasser|crever|detruire)\b/',
        '/\b(creve|crevez|creves)\b/',
        '/\bmort (a|aux) (tous )?(les )?\w+/',
        '/\bil faut (tous )?(les |le |la )?(tuer|exterminer|lyncher|pendre|fusiller|eliminer)\b/',
        '/\b(on va|je vais) (vous|te|le|la|les) (retrouver|faire payer)\b/',
        '/\b(a la|au) (guillotine|poteau|pendaison)\b/',
        // Appels a l'insurrection, a la lutte armee ou aux degradations : on oriente vers les voies pacifiques.
        '/\b(prenons|prenez|prendre) (les )?armes?\b/',
        '/\b(lutte|insurrection|rebellion|guerre) armee\b/',
        '/\b(renverser|renversons) (le|la|les|l) \w+ (par la force|par les armes|violemment)\b/',
        '/\b(il faut|on doit|allons|allez) (tout )?(bruler|incendier|saccager|casser|piller|attaquer|assaillir|bloquer par la force)\b/',
        '/\b(brulons|incendions|saccageons|pillons|attaquons) (la|le|les|l|leur|leurs|tout)\b/',
        '/\b(tous|toutes) (a|au) (l assaut|la bataille|l attaque)\b/',
        '/\bguerre civile (maintenant|des demain|c est la solution)\b/',
    ];

    /** Propos qui dégradent ou déshumanisent un groupe en raison de son origine, sa religion, son orientation ou son handicap. Une critique d'idées ou d'institutions n'est pas visée. */
    private const GROUPS = 'arabes?|noirs?|juifs?|juives?|musulmans?|musulmanes?|roms?|blancs?|asiatiques?|immigres?|etrangers?|migrants?|homosexuels?|gays?|lesbiennes?|trans|handicapes?|chretiens?|catholiques?|africains?';
    private const HATE_PATTERNS = [
        '/\b(sales?|sous|vermine|race de) (' . self::GROUPS . ')\b/',
        '/\b(les|des|ces) (' . self::GROUPS . ') (sont|etaient|restent) (tous |toutes )?(des )?(animaux|parasites|vermine|cancer|nuisibles|sous hommes|rats|cafards|degeneres|attardes|une maladie)\b/',
        '/\b(retournez|retourne|rentrez|rentre) (chez vous|chez toi|dans (votre|ton) pays)\b/',
        '/\b(exterminer|eradiquer) (tous )?(les |ces )(' . self::GROUPS . ')\b/',
        '/\b(les|des|ces) (' . self::GROUPS . ') (devraient|doivent) (tous )?(disparaitre|mourir|crever)\b/',
    ];

    /** @var string[] */
    private array $words;

    /** @param string[] $forbiddenWords */
    public function __construct(array $forbiddenWords = [], private readonly ?ModerationReporter $reporter = null, private readonly ?GeminiModerator $ai = null)
    {
        $this->words = array_values(array_filter(array_map(
            fn (string $w) => $this->normalize(str_replace('_', ' ', $w)),
            $forbiddenWords
        )));
    }

    public function containsInsult(string $text): bool
    {
        return $this->analyze($text) !== [];
    }

    /**
     * @return string[] liste de REASON_* detectes (vide si le texte est acceptable)
     */
    public function analyze(string $text): array
    {
        $reasons = [];
        $normalized = $this->normalize($text);

        $joined = $this->joinSpacedLetters($normalized);
        $tokens = $joined === '' ? [] : explode(' ', $joined);
        foreach ($this->words as $word) {
            if ($word === '') {
                continue;
            }
            if (preg_match('/(?<![a-z])' . preg_quote($word, '/') . '(e|es|s|x)?(?![a-z])/u', $joined)
                || ($this->isSingle($word) && $this->fuzzyHit($word, $tokens))) {
                $reasons[self::REASON_INSULT] = self::REASON_INSULT;
                break;
            }
        }

        foreach (self::THREAT_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized)) {
                $reasons[self::REASON_THREAT] = self::REASON_THREAT;
                break;
            }
        }

        foreach (self::HATE_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized)) {
                // La haine envers un groupe est aussi signalée comme insulte pour être bloquée partout où le filtre existe.
                $reasons[self::REASON_HATE] = self::REASON_HATE;
                $reasons[self::REASON_INSULT] = self::REASON_INSULT;
                break;
            }
        }

        // Telephone ou email d'un tiers dans un texte public : risque de harcelement.
        if (preg_match('/(?<!\d)(?:\+33|0033|0)\s?[1-9](?:[\s.\-]?\d{2}){4}(?!\d)/', $text)
            || preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text)) {
            $reasons[self::REASON_PERSONAL_DATA] = self::REASON_PERSONAL_DATA;
        }

        // Deuxième avis (facultatif) : seulement si le filtre local n'a rien trouvé, et seulement pour ajouter un blocage.
        if ($reasons === [] && $this->ai !== null) {
            $opinion = $this->ai->review($text);
            if ($opinion !== null && $opinion['verdict'] === 'bloquer' && $opinion['confidence'] >= GeminiModerator::BLOCK_CONFIDENCE && $opinion['reasons'] !== []) {
                foreach ($opinion['reasons'] as $r) {
                    $mapped = match ($r) { 'menace' => self::REASON_THREAT, 'haine' => self::REASON_HATE, default => self::REASON_INSULT };
                    $reasons[$mapped] = $mapped;
                }
                if (isset($reasons[self::REASON_HATE])) {
                    $reasons[self::REASON_INSULT] = self::REASON_INSULT;
                }
            } elseif ($opinion !== null && $opinion['verdict'] !== 'ok' && $opinion['confidence'] >= GeminiModerator::DOUBT_CONFIDENCE) {
                $this->reporter?->flagForReview($text, $opinion['reasons']);
            }
        }

        $found = array_values($reasons);
        if ($found !== []) {
            $this->reporter?->report($found, $text);
        }

        return $found;
    }

    /**
     * Message pedagogique a afficher a l'auteur (jamais d'accusation, toujours une piste).
     *
     * @param string[] $reasons
     */
    public function explain(array $reasons): string
    {
        $parts = [];
        if (in_array(self::REASON_INSULT, $reasons, true)) {
            $parts[] = 'Votre texte contient des termes insultants. Reformulez votre idee en critiquant les faits ou les mesures, pas les personnes.';
        }
        if (in_array(self::REASON_HATE, $reasons, true)) {
            $parts[] = 'Votre texte vise un groupe de personnes en raison de son origine, de sa religion ou de son identité. La charte de paix l\'interdit, et la loi punit la provocation à la haine. Vous pouvez critiquer des actes, des lois ou des institutions, avec des sources.';
        }
        if (in_array(self::REASON_THREAT, $reasons, true)) {
            $parts[] = 'Votre texte contient une menace ou un appel a la violence ou a l\'insurrection, ce qui est contraire a la charte de paix et a la loi. Pour agir efficacement et sans violence : petition, referendum d\'initiative partagee, rencontre avec vos elus (voir la page « Voies institutionnelles »).';
        }
        if (in_array(self::REASON_PERSONAL_DATA, $reasons, true)) {
            $parts[] = 'Votre texte contient un numero de telephone ou une adresse email. Retirez ces donnees personnelles avant de publier.';
        }

        return implode(' ', $parts);
    }

    private function isSingle(string $word): bool
    {
        return !str_contains($word, ' ');
    }

    /** Variantes à une faute près (lettre changée, oubliée ou ajoutée) pour les mots de 6 lettres ou plus : « conard », « connarrd ». */
    private function fuzzyHit(string $word, array $tokens): bool
    {
        $len = strlen($word);
        if ($len < 6) {
            return false;
        }
        foreach ($tokens as $t) {
            $l = strlen($t);
            if ($l >= $len - 1 && $l <= $len + 1 && $t !== '' && levenshtein($t, $word) <= 1) {
                return true;
            }
        }

        return false;
    }

    /** « c o n n a r d » ou « c.o.n.n.a.r.d » : recolle les suites de lettres isolées (3 ou plus). */
    private function joinSpacedLetters(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace_callback('/(?<![a-z])(?:[a-z](?: |$)){3,}/', fn ($m) => str_replace(' ', '', $m[0]) . ' ', $text) ?? $text) ?? $text);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, self::LEET);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = $ascii !== false ? $ascii : $text;
        // Séparateurs glissés entre les lettres (« c.o.n.n.a.r.d », « con*nard ») : remplacés par des espaces entre lettres isolées, sinon supprimés.
        $text = preg_replace('/(?<=\b[a-z])[\.\-_*+|,]+(?=[a-z]\b)/', ' ', $text) ?? $text;
        $text = preg_replace('/(?<=[a-z])[\.\-_*+|](?=[a-z])/', '', $text) ?? $text;
        $text = preg_replace('/[^a-z0-9\s]/', '', $text) ?? $text;
        // "connaaard" -> "connard" : on reduit les lettres repetees a 2 puis a 1 pour la comparaison.
        $text = preg_replace('/([a-z])\1{2,}/', '$1', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
