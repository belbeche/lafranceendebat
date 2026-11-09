<?php

namespace App\Service\Algo;

use App\Entity\ContentView;
use App\Entity\Debat;
use App\Service\Audience\ViewTracker;
use Doctrine\DBAL\Connection;

/**
 * Classement "tendance" des debats. Principes retenus (usage courant des plateformes de discussion) :
 *  1. decroissance exponentielle : une lecture compte moitie moins tous les 3 jours (demi-vie), pour que le classement
 *     suive l'actualite sans que les vieux sujets cumules ecrasent les nouveaux ;
 *  2. poids par type d'action : lecture 1, lecture engagee 3, argument depose 5 (participer vaut plus que lire) ;
 *  3. echelle logarithmique : les cent premieres lectures comptent plus que les cent suivantes ;
 *  4. qualite de lecture : multiplicateur de 0,6 a 1,4 selon la borne basse de Wilson de la part de lectures engagees,
 *     appliquee seulement si au moins 5 lectures ont ete mesurees. Un sujet cliqué mais quitte aussitot descend.
 * Aucun critere politique, aucun vote "pour/contre" ne compte : on mesure l'interet, pas l'opinion.
 */
class TrendingService
{
    public const HALF_LIFE_DAYS = 3.0;
    public const W_VIEW = 1.0;
    public const W_ENGAGED = 3.0;
    public const W_ARGUMENT = 5.0;

    public function __construct(private readonly ViewTracker $views, private readonly Connection $db)
    {
    }

    public static function decay(int $daysAgo): float
    {
        return 0.5 ** ($daysAgo / self::HALF_LIFE_DAYS);
    }

    /**
     * @param list<Debat> $debats
     * @return list<array{debat: Debat, score: float, rank: int}> tries du plus au moins suivi
     */
    public function rank(array $debats): array
    {
        $ids = array_values(array_filter(array_map(static fn (Debat $d) => $d->getId(), $debats)));
        $daily = $this->views->recentDaily(ContentView::DEBAT, $ids);
        $stats = $this->views->stats(ContentView::DEBAT, $ids);
        $args = $this->argumentsByAge($ids);
        $rows = [];
        foreach ($debats as $d) {
            $id = (int) $d->getId();
            $raw = 0.0;
            foreach ($daily[$id] ?? [] as $row) {
                $raw += self::decay($row['ago']) * (self::W_VIEW * $row['views'] + self::W_ENGAGED * $row['engaged']);
            }
            foreach ($args[$id] ?? [] as $ago) {
                $raw += self::decay($ago) * self::W_ARGUMENT;
            }
            $s = $stats[$id] ?? null;
            $mult = 1.0;
            if ($s && $s['enough']) {
                $mult = 0.6 + 0.8 * ViewTracker::wilsonLower((int) round($s['engagedPct'] * $s['samples'] / 100), $s['samples']);
            }
            $rows[] = ['debat' => $d, 'score' => round(log(1 + $raw) * $mult, 4), 'rank' => 0];
        }
        usort($rows, static fn ($a, $b) => [$b['score'], $b['debat']->getCreatedAt()] <=> [$a['score'], $a['debat']->getCreatedAt()]);
        foreach ($rows as $i => &$r) {
            $r['rank'] = $i + 1;
        }

        return $rows;
    }

    /** @param list<int> $ids @return array<int, list<int>> age en jours de chaque argument valide des 14 derniers jours */
    private function argumentsByAge(array $ids): array
    {
        $out = [];
        if (!$ids) {
            return $out;
        }
        $since = (new \DateTimeImmutable('today'))->modify('-14 days')->format('Y-m-d H:i:s');
        $today = new \DateTimeImmutable('today');
        foreach ($this->db->fetchAllAssociative('SELECT debat_id, created_at FROM argument WHERE status = ? AND created_at >= ? AND debat_id IN (' . implode(',', array_map('intval', $ids)) . ')', ['Validée', $since]) as $r) {
            $out[(int) $r['debat_id']][] = (int) $today->diff(new \DateTimeImmutable(substr((string) $r['created_at'], 0, 10)))->days;
        }

        return $out;
    }
}
