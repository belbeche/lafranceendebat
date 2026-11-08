<?php

namespace App\Service\Algo;

use App\Entity\Proposition;
use App\Repository\PropositionRepository;

/**
 * Propositions proches : TF-IDF + similarite cosinus sur titre (poids 2) + description + theme.
 * Methode classique de detection de doublons ; calculee a la volee sur les propositions validees
 * (quelques centaines au plus), sans service externe ni apprentissage. Sert a :
 *  - proposer de soutenir une proposition existante plutot que d'en creer une presque identique ;
 *  - afficher "Propositions proches" sous chaque fiche, pour regrouper l'energie des citoyens.
 */
class SimilarityService
{
    public const POOL = 600;
    public const DUPLICATE_THRESHOLD = 0.55;
    public const RELATED_THRESHOLD = 0.18;
    private const STOP = ['le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'd', 'l', 'et', 'ou', 'en', 'au', 'aux', 'a', 'à', 'pour', 'par', 'sur', 'dans', 'avec', 'sans', 'que', 'qui', 'quoi', 'dont', 'ce', 'cet', 'cette', 'ces', 'se', 'sa', 'son', 'ses', 'leur', 'leurs', 'est', 'sont', 'etre', 'être', 'plus', 'moins', 'pas', 'ne', 'il', 'elle', 'on', 'nous', 'vous', 'ils', 'elles', 'tout', 'tous', 'toute', 'toutes', 'si', 'mais', 'donc', 'car', 'afin', 'comme', 'entre', 'vers', 'chez', 'très', 'tres', 'aussi', 'faire', 'fait', 'mettre', 'mise', 'place'];

    public function __construct(private readonly PropositionRepository $props)
    {
    }

    /** @return list<string> */
    public static function tokens(string $text): array
    {
        $t = mb_strtolower(strip_tags($text));
        $t = strtr($t, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe']);
        $out = [];
        foreach (preg_split('/[^a-z0-9]+/', $t, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
            if (strlen($w) < 3 || in_array($w, self::STOP, true)) {
                continue;
            }
            // racinisation tres legere : pluriels et terminaisons courantes
            $w = preg_replace('/(ements|ement|ations|ation|ités|ite|ites|es|s|x)$/', '', $w) ?: $w;
            if (strlen($w) >= 3) {
                $out[] = $w;
            }
        }

        return $out;
    }

    private static function doc(string $title, string $body, string $theme): array
    {
        return array_merge(self::tokens($title), self::tokens($title), self::tokens($body), self::tokens($theme));
    }

    /**
     * Propositions les plus proches d'un texte (avant creation) ou d'une proposition existante.
     * @return list<array{p: Proposition, score: float}>
     */
    public function nearest(string $title, string $body, string $theme = '', ?int $excludeId = null, int $limit = 4, float $min = self::RELATED_THRESHOLD): array
    {
        $pool = array_values(array_filter($this->props->findValidatedRanked(null, self::POOL), static fn (Proposition $p) => $p->getId() !== $excludeId));
        $docs = [];
        foreach ($pool as $i => $p) {
            $docs[$i] = array_count_values(self::doc((string) $p->getTitle(), (string) $p->getDescription(), (string) $p->getTheme()));
        }
        $query = array_count_values(self::doc($title, $body, $theme));
        if (!$query || !$docs) {
            return [];
        }
        $n = count($docs) + 1;
        $df = [];
        foreach ([...$docs, $query] as $d) {
            foreach (array_keys($d) as $term) {
                $df[$term] = ($df[$term] ?? 0) + 1;
            }
        }
        $vec = static function (array $tf) use ($df, $n): array {
            $v = [];
            foreach ($tf as $term => $c) {
                $v[$term] = (1 + log($c)) * log(1 + $n / $df[$term]);
            }

            return $v;
        };
        $norm = static fn (array $v): float => sqrt(array_sum(array_map(static fn ($x) => $x * $x, $v))) ?: 1.0;
        $q = $vec($query);
        $qn = $norm($q);
        $res = [];
        foreach ($docs as $i => $tf) {
            $v = $vec($tf);
            $dot = 0.0;
            foreach ($q as $term => $w) {
                if (isset($v[$term])) {
                    $dot += $w * $v[$term];
                }
            }
            $score = $dot / ($qn * $norm($v));
            if ($score >= $min) {
                $res[] = ['p' => $pool[$i], 'score' => round($score, 3)];
            }
        }
        usort($res, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($res, 0, $limit);
    }

    /** @return list<array{p: Proposition, score: float}> */
    public function relatedTo(Proposition $p, int $limit = 4): array
    {
        return $this->nearest((string) $p->getTitle(), (string) $p->getDescription(), (string) $p->getTheme(), $p->getId(), $limit);
    }
}
