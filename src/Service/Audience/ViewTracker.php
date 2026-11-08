<?php

namespace App\Service\Audience;

use App\Entity\ContentView;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lectures des publications : compteur de vues (sans cookie, une par lecteur et par jour), et, avec consentement,
 * temps de lecture et defilement. Les statistiques sont publiques et agregees.
 */
class ViewTracker
{
    private const BOTS = '/bot|crawl|spider|slurp|curl|wget|preview|headless|monitor|python|java\/|scrapy|facebookexternalhit|lighthouse/i';
    /** Une lecture est "engagee" si le lecteur a lu >= 20 s en parcourant la moitie de la page, ou >= 60 s. */
    private const ENGAGED_SQL = '(read_seconds >= 20 AND scroll_pct >= 50) OR read_seconds >= 60';

    public function __construct(private readonly EntityManagerInterface $em, private readonly Connection $db, private readonly string $secret)
    {
    }

    public function key(Request $r): string
    {
        return sha1(($r->getClientIp() ?? '0') . '|' . ($r->headers->get('User-Agent') ?? '') . '|' . $this->secret . '|' . date('Y-m-d'));
    }

    public function isBot(Request $r): bool
    {
        $ua = (string) $r->headers->get('User-Agent', '');

        return '' === $ua || (bool) preg_match(self::BOTS, $ua) || 'prefetch' === strtolower((string) $r->headers->get('Sec-Purpose', ''));
    }

    /** Compte la lecture du jour (idempotent). Retourne true si une nouvelle lecture a ete enregistree. */
    public function recordView(string $kind, int $id, Request $r): bool
    {
        if ($this->isBot($r) || !$r->isMethod('GET')) {
            return false;
        }
        $key = $this->key($r);
        $day = (new \DateTimeImmutable())->format('Y-m-d');
        if ($this->db->fetchOne('SELECT 1 FROM content_view WHERE kind = ? AND target_id = ? AND day = ? AND visitor_key = ?', [$kind, $id, $day, $key])) {
            return false;
        }
        try {
            $this->em->persist(new ContentView($kind, $id, $key));
            $this->em->flush();
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /** Met a jour le temps de lecture du jour (appele par la balise de mesure, uniquement avec consentement). */
    public function recordRead(string $kind, int $id, Request $r, int $seconds, int $scroll): bool
    {
        if ($this->isBot($r)) {
            return false;
        }
        $day = (new \DateTimeImmutable())->format('Y-m-d');
        $row = $this->db->fetchAssociative('SELECT id, read_seconds, scroll_pct FROM content_view WHERE kind = ? AND target_id = ? AND day = ? AND visitor_key = ?', [$kind, $id, $day, $this->key($r)]);
        if (!$row) {
            return false;
        }
        $this->db->update('content_view', ['read_seconds' => max((int) $row['read_seconds'], max(0, min(1800, $seconds))), 'scroll_pct' => max((int) $row['scroll_pct'], max(0, min(100, $scroll)))], ['id' => $row['id']]);

        return true;
    }

    /**
     * Statistiques publiques par publication.
     * @param list<int> $ids
     * @return array<int, array{views: int, samples: int, avgSeconds: int, totalSeconds: int, engagedPct: int, bouncePct: int, label: string, enough: bool}>
     */
    public function stats(string $kind, array $ids): array
    {
        $out = [];
        if (!$ids) {
            return $out;
        }
        $rows = $this->db->fetchAllAssociative(
            'SELECT target_id, COUNT(*) AS views, SUM(CASE WHEN read_seconds > 0 THEN 1 ELSE 0 END) AS samples, SUM(read_seconds) AS secs,'
            . ' SUM(CASE WHEN ' . self::ENGAGED_SQL . ' THEN 1 ELSE 0 END) AS engaged,'
            . ' SUM(CASE WHEN read_seconds > 0 AND read_seconds < 10 THEN 1 ELSE 0 END) AS bounced'
            . ' FROM content_view WHERE kind = ? AND target_id IN (' . implode(',', array_map('intval', $ids)) . ') GROUP BY target_id',
            [$kind]
        );
        foreach ($rows as $r) {
            $samples = (int) $r['samples'];
            $engaged = (int) $r['engaged'];
            $wilson = self::wilsonLower($engaged, $samples);
            $enough = $samples >= 5;
            $out[(int) $r['target_id']] = [
                'views' => (int) $r['views'],
                'samples' => $samples,
                'avgSeconds' => $samples ? (int) round(((int) $r['secs']) / $samples) : 0,
                'totalSeconds' => (int) $r['secs'],
                'engagedPct' => $samples ? (int) round(100 * $engaged / $samples) : 0,
                'bouncePct' => $samples ? (int) round(100 * ((int) $r['bounced']) / $samples) : 0,
                'label' => !$enough ? 'Pas encore assez de lectures mesurées' : ($wilson >= 0.5 ? 'Très suivi' : ($wilson >= 0.3 ? 'Suivi' : 'Peu lu jusqu\'au bout')),
                'enough' => $enough,
            ];
        }

        return $out;
    }

    /** Lectures par jour sur la fenetre, pour l'algorithme de tendance. @param list<int> $ids @return array<int, list<array{ago: int, views: int, engaged: int}>> */
    public function recentDaily(string $kind, array $ids, int $days = 14): array
    {
        $out = [];
        if (!$ids) {
            return $out;
        }
        $since = (new \DateTimeImmutable('today'))->modify('-' . $days . ' days')->format('Y-m-d');
        $rows = $this->db->fetchAllAssociative(
            'SELECT target_id, day, COUNT(*) AS views, SUM(CASE WHEN ' . self::ENGAGED_SQL . ' THEN 1 ELSE 0 END) AS engaged'
            . ' FROM content_view WHERE kind = ? AND day >= ? AND target_id IN (' . implode(',', array_map('intval', $ids)) . ') GROUP BY target_id, day',
            [$kind, $since]
        );
        $today = new \DateTimeImmutable('today');
        foreach ($rows as $r) {
            $ago = (int) $today->diff(new \DateTimeImmutable(substr((string) $r['day'], 0, 10)))->days;
            $out[(int) $r['target_id']][] = ['ago' => $ago, 'views' => (int) $r['views'], 'engaged' => (int) $r['engaged']];
        }

        return $out;
    }

    /** Borne basse de l'intervalle de Wilson (z = 1,96) : une part observee sur peu de lectures pese peu. */
    public static function wilsonLower(int $positive, int $n): float
    {
        if ($n <= 0) {
            return 0.0;
        }
        $z = 1.96;
        $p = $positive / $n;

        return max(0.0, ($p + $z * $z / (2 * $n) - $z * sqrt(($p * (1 - $p) + $z * $z / (4 * $n)) / $n)) / (1 + $z * $z / $n));
    }

    public static function duration(int $s): string
    {
        if ($s < 60) {
            return $s . ' s';
        }
        $m = intdiv($s, 60);
        if ($m < 60) {
            return $m . ' min' . ($s % 60 ? ' ' . ($s % 60) . ' s' : '');
        }

        return intdiv($m, 60) . ' h ' . ($m % 60) . ' min';
    }

    public function purge(): int
    {
        return (int) $this->db->executeStatement('DELETE FROM content_view WHERE day < ?', [(new \DateTimeImmutable('-13 months'))->format('Y-m-d')]);
    }
}
