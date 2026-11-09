<?php

namespace App\Service\Programme;

/**
 * Logique de chiffrage d'une proposition : un calcul EXPLICITE et reproductible, jamais un chiffre sorti d'un chapeau.
 *
 *   coût annuel (M€) = population concernée × coût unitaire annuel (€) × taux de recours (%) ÷ 100 ÷ 1 000 000
 *                      + coût fixe (M€) − contrepartie (économies, recettes) (M€)
 *
 * Chaque paramètre a une valeur basse, centrale et haute. Le calcul est fait ICI, en code, à partir des paramètres :
 * l'IA propose des hypothèses (avec leur justification), elle ne fournit jamais le résultat.
 *  - scénario bas   : paramètres bas, contrepartie haute
 *  - scénario haut  : paramètres hauts, contrepartie basse
 * Bornes de sécurité : toute valeur hors bornes (population > 100 millions, taux > 100 %...) fait refuser le jeu de paramètres.
 */
final class CostModel
{
    public const BOUNDS = [
        'population' => [0, 100_000_000],
        'unit_cost_eur' => [0, 10_000_000],
        'take_up_pct' => [0, 100],
        'fixed_millions' => [-1_000_000, 1_000_000],
        'offset_millions' => [-1_000_000, 1_000_000],
    ];
    public const LABELS = [
        'population' => 'Personnes ou structures concernées',
        'unit_cost_eur' => 'Coût annuel par personne ou structure (€)',
        'take_up_pct' => 'Part qui en bénéficie réellement (%)',
        'fixed_millions' => 'Coût fixe annuel (M€)',
        'offset_millions' => 'Contrepartie : économies ou recettes (M€)',
    ];

    /**
     * @param array<string,mixed> $params chaque clé de BOUNDS => ['low'=>n,'central'=>n,'high'=>n,'basis'=>string]
     *                                    population, unit_cost_eur, take_up_pct obligatoires ; fixed et offset facultatifs (0)
     *
     * @return array{low:int,central:int,high:int}|null null si les paramètres sont absents, incohérents ou hors bornes
     */
    public static function compute(array $params): ?array
    {
        $p = self::normalize($params);
        if (null === $p) {
            return null;
        }
        $calc = static fn (string $case, string $offsetCase): float => $p['population'][$case] * $p['unit_cost_eur'][$case] * $p['take_up_pct'][$case] / 100 / 1_000_000
            + $p['fixed_millions'][$case] - $p['offset_millions'][$offsetCase];
        $values = [(int) round($calc('low', 'high')), (int) round($calc('central', 'central')), (int) round($calc('high', 'low'))];
        sort($values);

        return ['low' => $values[0], 'central' => (int) round(self::central($p)), 'high' => $values[2]];
    }

    private static function central(array $p): float
    {
        return $p['population']['central'] * $p['unit_cost_eur']['central'] * $p['take_up_pct']['central'] / 100 / 1_000_000 + $p['fixed_millions']['central'] - $p['offset_millions']['central'];
    }

    /** @return array<string,array{low:float,central:float,high:float,basis:string}>|null */
    public static function normalize(array $params): ?array
    {
        $out = [];
        foreach (self::BOUNDS as $key => [$min, $max]) {
            $required = in_array($key, ['population', 'unit_cost_eur', 'take_up_pct'], true);
            $row = $params[$key] ?? null;
            if (!is_array($row)) {
                if ($required) {
                    return null;
                }
                $out[$key] = ['low' => 0.0, 'central' => 0.0, 'high' => 0.0, 'basis' => ''];
                continue;
            }
            $vals = [];
            foreach (['low', 'central', 'high'] as $c) {
                if (!isset($row[$c]) || !is_numeric($row[$c])) {
                    return null;
                }
                $v = (float) $row[$c];
                if ($v < $min || $v > $max) {
                    return null;
                }
                $vals[$c] = $v;
            }
            if ($vals['low'] > $vals['central'] || $vals['central'] > $vals['high']) {
                return null; // bas <= central <= haut
            }
            $out[$key] = $vals + ['basis' => mb_substr(trim((string) ($row['basis'] ?? '')), 0, 300)];
        }

        return $out;
    }

    /** La formule avec les valeurs centrales, lisible par un citoyen. */
    public static function explain(array $params): ?string
    {
        $p = self::normalize($params);
        if (null === $p) {
            return null;
        }
        $f = static fn (float $n): string => number_format($n, $n == floor($n) ? 0 : 1, ',', ' ');
        $s = sprintf('%s personnes × %s € × %s %% de recours', $f($p['population']['central']), $f($p['unit_cost_eur']['central']), $f($p['take_up_pct']['central']));
        if ($p['fixed_millions']['central'] > 0) {
            $s .= ' + ' . $f($p['fixed_millions']['central']) . ' M€ de coût fixe';
        }
        if ($p['offset_millions']['central'] > 0) {
            $s .= ' − ' . $f($p['offset_millions']['central']) . ' M€ de contrepartie';
        }

        return $s;
    }
}
