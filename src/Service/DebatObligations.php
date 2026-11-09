<?php

namespace App\Service;

/**
 * Obligations d'un debat vis-a-vis des sources officielles. Chaque point est "respecte", "en cours" ou "pas encore".
 * Les 4 premiers conditionnent la relecture ; les deux derniers sont recommandes.
 */
class DebatObligations
{
    public const OK = 'ok';
    public const WIP = 'wip';
    public const TODO = 'todo';
    public const LABELS = [self::OK => 'Respecté', self::WIP => 'En cours', self::TODO => 'Pas encore'];

    /** Domaines officiels ou institutionnels reconnus (le domaine ou un sous-domaine). */
    public const OFFICIAL = ['gouv.fr', 'insee.fr', 'ccomptes.fr', 'cour-des-comptes.fr', 'vie-publique.fr', 'service-public.fr', 'assemblee-nationale.fr', 'senat.fr', 'conseil-constitutionnel.fr', 'conseil-etat.fr', 'europa.eu', 'cnil.fr', 'hatvp.fr', 'defenseurdesdroits.fr', 'francetravail.fr', 'banque-france.fr', 'ameli.fr', 'anil.org', 'ademe.fr', 'santepubliquefrance.fr', 'has-sante.fr', 'oecd.org', 'un.org', 'eurostat.ec.europa.eu'];
    public const GENERIC_SOURCES = ['https://www.insee.fr', 'https://www.vie-publique.fr', 'https://www.legifrance.gouv.fr', 'https://www.data.gouv.fr', 'https://www.ccomptes.fr'];

    public static function host(string $url): string
    {
        return strtolower((string) preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)));
    }

    public static function isOfficial(string $url): bool
    {
        $h = self::host($url);
        foreach (self::OFFICIAL as $d) {
            if ($h === $d || str_ends_with($h, '.' . $d)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> liens officiels recommandes pour le theme */
    public static function sourcesFor(?string $theme): array
    {
        return $theme && isset(DossierCatalog::STARTERS[$theme]) ? DossierCatalog::STARTERS[$theme][2] : self::GENERIC_SOURCES;
    }

    /** @return list<array{key: string, label: string, status: string, statusLabel: string, hint: string, required: bool}> */
    public function evaluate(string $title, ?string $theme, string $summary, string $facts, string $authority): array
    {
        $title = trim($title);
        $summary = trim($summary);
        $facts = trim($facts);
        preg_match_all('#https://[^\s<>"\']+\.[^\s<>"\']+#i', $facts, $m);
        $urls = array_unique($m[0]);
        $official = array_values(array_filter($urls, [self::class, 'isOfficial']));
        $themeHosts = array_map([self::class, 'host'], self::sourcesFor($theme));
        $themeHit = array_filter($official, static fn (string $u) => in_array(self::host($u), $themeHosts, true));

        $tl = mb_strlen($title);
        $q = !$tl ? self::TODO : (($tl >= 10 && $tl <= 180 && (str_contains($title, '?') || preg_match('/^(comment|pourquoi|faut-il|doit-on|peut-on|quel|quelle|quels|quelles|que|qui|combien|où|quand)\b/iu', $title))) ? self::OK : self::WIP);
        $sl = mb_strlen($summary);
        $e = !$sl ? self::TODO : ($sl >= 100 ? self::OK : self::WIP);
        $s = !$urls ? self::TODO : ($official ? self::OK : self::WIP);
        $hasFigure = (bool) preg_match('/\b(19|20)\d{2}\b|\d+\s?(%|€|euros?|milliards?|millions?)/iu', $facts);
        $f = '' === $facts ? self::TODO : ($hasFigure ? self::OK : self::WIP);
        $a = '' !== trim($authority) ? self::OK : self::TODO;
        $t = $themeHit ? self::OK : ($official ? self::WIP : self::TODO);

        $mk = static fn (string $key, string $label, string $st, string $hint, bool $req): array => ['key' => $key, 'label' => $label, 'status' => $st, 'statusLabel' => self::LABELS[$st], 'hint' => $hint, 'required' => $req];

        return [
            $mk('question', 'Une question claire et neutre', $q, 'Formulez le débat comme une question (10 à 180 caractères), sans parti pris.', true),
            $mk('expose', 'Un exposé neutre du problème', $e, 'Au moins 100 caractères : la situation, sans viser une personne.', true),
            $mk('source', 'Au moins une source officielle (lien https)', $s, $urls && !$official ? 'Vos liens ne sont pas reconnus comme officiels. Ajoutez INSEE, Légifrance, Cour des comptes, vie-publique.fr...' : 'Collez un lien vers un site officiel (INSEE, Légifrance, ministère...).', true),
            $mk('chiffres', 'Des faits datés et chiffrés', $f, 'Indiquez au moins une date (année) ou un chiffre (%, €, nombre) avec sa source.', true),
            $mk('theme', 'Une source officielle propre au thème', $t, 'Consultez les sources recommandées pour ce thème et citez-en une.', false),
            $mk('autorite', 'L\'autorité compétente est indiquée', $a, 'Qui peut décider ? (commune, région, ministère, Parlement, Europe...)', false),
        ];
    }

    /** @param list<array{status: string, required: bool}> $items */
    public static function ready(array $items): bool
    {
        foreach ($items as $i) {
            if ($i['required'] && self::OK !== $i['status']) {
                return false;
            }
        }

        return true;
    }
}
