<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/** Statistiques d'administration : chaque bloc est independant, une erreur sur l'un n'empeche pas les autres. */
class AdminStats
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly LoggerInterface $logger)
    {
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return [
            'members' => $this->safe('members'), 'signups' => $this->safe('signups'), 'content' => $this->safe('content'), 'top' => $this->safe('top'),
            'contributors' => $this->safe('contributors'), 'audience' => $this->safe('audience'), 'activity' => $this->safe('activity'),
            'security' => $this->safe('security'), 'lettre' => $this->safe('lettre'), 'surveys' => $this->safe('surveys'),
            'commerce' => $this->safe('commerce'), 'agenda' => $this->safe('agenda'), 'moderation' => $this->safe('moderation'),
        ];
    }

    /** @return array<string,mixed> */
    private function safe(string $block): array
    {
        try {
            return $this->$block();
        } catch (\Throwable $e) {
            $this->logger->warning('Statistiques : bloc ' . $block . ' indisponible : ' . $e->getMessage());

            return [];
        }
    }

    private function scalar(string $dql, array $params = []): int
    {
        return (int) $this->em->createQuery($dql)->setParameters($params)->getSingleScalarResult();
    }

    /** @return array<string,int> */
    private function grouped(string $dql, array $params = []): array
    {
        $out = [];
        foreach ($this->em->createQuery($dql)->setParameters($params)->getArrayResult() as $r) {
            $out[(string) ($r['k'] ?? '?')] = (int) $r['n'];
        }

        return $out;
    }

    /** @return array<string,int> jours (Y-m-d) => effectif, sur $days jours, zeros inclus */
    private function perDay(string $dql, int $days, array $params = []): array
    {
        $series = [];
        for ($i = $days - 1; $i >= 0; --$i) {
            $series[date('Y-m-d', strtotime("-$i days"))] = 0;
        }
        $params['since'] = new \DateTimeImmutable('-' . $days . ' days midnight');
        foreach ($this->em->createQuery($dql)->setParameters($params)->getResult() as $d) {
            $d = is_array($d) ? reset($d) : $d;
            $k = $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : substr((string) $d, 0, 10);
            if (isset($series[$k])) {
                ++$series[$k];
            }
        }

        return $series;
    }

    private function members(): array
    {
        $U = 'App\Entity\User u';
        $d7 = new \DateTimeImmutable('-7 days');
        $d30 = new \DateTimeImmutable('-30 days');
        $total = $this->scalar("SELECT COUNT(u.id) FROM $U");

        return [
            'total' => $total,
            'new7' => $this->scalar("SELECT COUNT(u.id) FROM $U WHERE u.createdAt >= :d", ['d' => $d7]),
            'new30' => $this->scalar("SELECT COUNT(u.id) FROM $U WHERE u.createdAt >= :d", ['d' => $d30]),
            'active30' => $this->scalar("SELECT COUNT(u.id) FROM $U WHERE u.lastLogin >= :d", ['d' => $d30]),
            'verified' => $this->scalar("SELECT COUNT(u.id) FROM $U WHERE u.isVerified = true"),
            'restricted' => $this->scalar("SELECT COUNT(u.id) FROM $U WHERE u.restrictedAt IS NOT NULL"),
            'disabled' => $this->scalar("SELECT COUNT(u.id) FROM $U WHERE u.isActive = false OR u.isEnabled = false"),
            'roles' => [
                'Associations' => $this->scalar("SELECT COUNT(u.id) FROM $U WHERE u.roles LIKE '%ROLE_ASSOCIATION%'"),
                'Entreprises et experts' => $this->scalar("SELECT COUNT(u.id) FROM $U WHERE u.roles LIKE '%ROLE_ACTOR%'"),
                'Élus' => $this->scalar("SELECT COUNT(u.id) FROM $U WHERE u.roles LIKE '%ROLE_ELU%'"),
                'Modérateurs' => $this->scalar("SELECT COUNT(u.id) FROM $U WHERE u.roles LIKE '%ROLE_MODERATOR%'"),
                'Administrateurs' => $this->scalar("SELECT COUNT(u.id) FROM $U WHERE u.roles LIKE '%ROLE_ADMIN%'"),
            ],
        ];
    }

    private function signups(): array
    {
        return ['days' => $this->perDay('SELECT u.createdAt FROM App\Entity\User u WHERE u.createdAt >= :since', 30)];
    }

    private function content(): array
    {
        return [
            'propositions' => $this->grouped('SELECT p.status k, COUNT(p.id) n FROM App\Entity\Proposition p GROUP BY p.status'),
            'themes' => $this->grouped("SELECT p.theme k, COUNT(p.id) n FROM App\Entity\Proposition p WHERE p.status = 'Validée' GROUP BY p.theme ORDER BY n DESC"),
            'supports' => $this->scalar('SELECT COALESCE(SUM(p.supportCount), 0) FROM App\Entity\Proposition p'),
            'votes' => $this->scalar('SELECT COUNT(v.id) FROM App\Entity\Vote v'),
            'debats' => $this->grouped('SELECT d.status k, COUNT(d.id) n FROM App\Entity\Debat d GROUP BY d.status'),
            'arguments' => $this->grouped('SELECT a.stance k, COUNT(a.id) n FROM App\Entity\Argument a GROUP BY a.stance'),
            'discussions' => $this->scalar('SELECT COUNT(x.id) FROM App\Entity\Discussion x'),
            'replies' => $this->scalar('SELECT COUNT(x.id) FROM App\Entity\DiscussionReply x'),
            'amendements' => $this->scalar('SELECT COUNT(x.id) FROM App\Entity\Amendement x'),
            'endorsements' => $this->scalar('SELECT COUNT(x.id) FROM App\Entity\ActorEndorsement x'),
            'props30' => $this->perDay('SELECT p.createdAt FROM App\Entity\Proposition p WHERE p.createdAt >= :since', 30),
        ];
    }

    private function top(): array
    {
        return $this->em->createQuery("SELECT p.id, p.title, p.theme, p.supportCount FROM App\Entity\Proposition p WHERE p.status = 'Validée' ORDER BY p.supportCount DESC, p.id DESC")->setMaxResults(10)->getArrayResult();
    }

    private function contributors(): array
    {
        $rows = $this->em->createQuery("SELECT u.firstName f, u.lastName l, COUNT(p.id) n FROM App\Entity\Proposition p JOIN p.author u WHERE p.status = 'Validée' GROUP BY u.id, u.firstName, u.lastName ORDER BY n DESC")->setMaxResults(10)->getArrayResult();

        return array_map(static fn ($r) => ['name' => trim($r['f'] . ' ' . mb_substr((string) $r['l'], 0, 1) . '.'), 'n' => (int) $r['n']], $rows);
    }

    private function audience(): array
    {
        $repo = $this->em->getRepository(\App\Entity\SiteVisitor::class);

        return [
            'today' => $repo->getTodayVisitorsCount(), 'week' => $repo->getVisitorsCountSince(new \DateTimeImmutable('-7 days')),
            'month' => $repo->getVisitorsCountSince(new \DateTimeImmutable('-30 days')), 'total' => $repo->getTotalUniqueVisitors(),
            'pages' => array_slice($repo->getPopularPages(), 0, 10), 'countries' => array_slice($repo->getVisitorsByCountry(), 0, 10),
        ];
    }

    private function activity(): array
    {
        $E = 'App\Entity\UserEvent e';

        return [
            'logins14' => $this->perDay("SELECT e.at FROM $E WHERE e.type = 'login_ok' AND e.at >= :since", 14),
            'pages14' => $this->perDay("SELECT e.at FROM $E WHERE e.type = 'page' AND e.at >= :since", 14),
            'topPages' => $this->grouped("SELECT e.path k, COUNT(e.id) n FROM $E WHERE e.type = 'page' AND e.path IS NOT NULL AND e.at >= :s GROUP BY e.path ORDER BY n DESC", ['s' => new \DateTimeImmutable('-30 days')]),
            'countries' => $this->grouped("SELECT e.country k, COUNT(DISTINCT e.user) n FROM $E WHERE e.type = 'login_ok' AND e.country IS NOT NULL AND e.at >= :s GROUP BY e.country ORDER BY n DESC", ['s' => new \DateTimeImmutable('-30 days')]),
            'devices' => $this->grouped("SELECT e.device k, COUNT(e.id) n FROM $E WHERE e.type = 'login_ok' AND e.device IS NOT NULL AND e.at >= :s GROUP BY e.device ORDER BY n DESC", ['s' => new \DateTimeImmutable('-30 days')]),
        ];
    }

    private function security(): array
    {
        return ['events30' => $this->grouped("SELECT e.type k, COUNT(e.id) n FROM App\Entity\UserEvent e WHERE e.type NOT IN ('page', 'login_ok') AND e.at >= :s GROUP BY e.type ORDER BY n DESC", ['s' => new \DateTimeImmutable('-30 days')])];
    }

    private function lettre(): array
    {
        $S = 'App\Entity\NewsletterSubscriber s';

        return [
            'confirmed' => $this->scalar("SELECT COUNT(s.id) FROM $S WHERE s.isActive = true AND s.confirmedAt IS NOT NULL AND s.unsubscribedAt IS NULL"),
            'pending' => $this->scalar("SELECT COUNT(s.id) FROM $S WHERE s.confirmedAt IS NULL"),
            'unsub' => $this->scalar("SELECT COUNT(s.id) FROM $S WHERE s.unsubscribedAt IS NOT NULL"),
            'lettres' => $this->grouped('SELECT l.status k, COUNT(l.id) n FROM App\Entity\Lettre l GROUP BY l.status'),
        ];
    }

    private function surveys(): array
    {
        return [
            'byStatus' => $this->grouped('SELECT s.status k, COUNT(s.id) n FROM App\Entity\Survey s GROUP BY s.status'),
            'responses' => $this->scalar('SELECT COUNT(r.id) FROM App\Entity\SurveyResponse r'),
            'invited' => $this->scalar('SELECT COALESCE(SUM(s.sentCount), 0) FROM App\Entity\Survey s'),
        ];
    }

    private function commerce(): array
    {
        $orders = $this->em->createQuery('SELECT o.status k, COUNT(o.id) n, COALESCE(SUM(o.amountCents), 0) amount, COALESCE(SUM(o.commissionCents), 0) commission FROM App\Entity\ServiceOrder o GROUP BY o.status')->getArrayResult();
        $donations = $this->em->createQuery('SELECT d.status k, COUNT(d.id) n, COALESCE(SUM(d.amountCents), 0) amount FROM App\Entity\Donation d GROUP BY d.status')->getArrayResult();

        return ['orders' => $orders, 'donations' => $donations];
    }

    private function agenda(): array
    {
        $today = new \DateTime('today');

        return [
            'upcoming' => $this->scalar("SELECT COUNT(e.id) FROM App\Entity\Event e WHERE e.date >= :t AND e.status NOT IN ('pending', 'rejected')", ['t' => $today]),
            'pending' => $this->scalar("SELECT COUNT(e.id) FROM App\Entity\Event e WHERE e.status = 'pending'"),
            'imported' => $this->scalar('SELECT COUNT(e.id) FROM App\Entity\Event e WHERE e.source IS NOT NULL'),
            'byKind' => $this->grouped('SELECT e.kind k, COUNT(e.id) n FROM App\Entity\Event e GROUP BY e.kind'),
        ];
    }

    private function moderation(): array
    {
        return [
            'signalements' => $this->grouped('SELECT s.status k, COUNT(s.id) n FROM App\Entity\Signalement s GROUP BY s.status'),
            'roleRequests' => $this->grouped('SELECT r.status k, COUNT(r.id) n FROM App\Entity\RoleRequest r GROUP BY r.status'),
            'pendingProps' => $this->scalar("SELECT COUNT(p.id) FROM App\Entity\Proposition p WHERE p.status = 'En attente'"),
        ];
    }
}
