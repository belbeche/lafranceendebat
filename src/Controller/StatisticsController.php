<?php

namespace App\Controller;

use App\Entity\{ActorContact, Debat, Dossier, NewsItem, Proposition, Revendication, User};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Statistiques publiques de la plateforme (inspirées d'une page "statistiques des établissements") :
 * chiffres clés, propositions par thème, suivi des revendications transmises aux institutions.
 * Aucune donnée personnelle : uniquement des totaux. Export CSV du tableau par thème.
 */
class StatisticsController extends AbstractController
{
    #[Route('/statistiques', name: 'app_statistics', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('statistics/index.html.twig', $this->data($em));
    }

    #[Route('/statistiques/themes.csv', name: 'app_statistics_csv', methods: ['GET'])]
    public function csv(EntityManagerInterface $em): Response
    {
        $d = $this->data($em);
        $r = new StreamedResponse(function () use ($d): void {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, ['theme', 'propositions_validees', 'soutiens', 'debats_ouverts', 'revendications'], ';');
            foreach ($d['themes'] as $t) {
                fputcsv($o, [$t['theme'], $t['propositions'], $t['soutiens'], $t['debats'], $t['revendications']], ';');
            }
            fclose($o);
        });
        $r->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $r->headers->set('Content-Disposition', 'attachment; filename="statistiques-par-theme-' . date('Y-m-d') . '.csv"');

        return $r;
    }

    /** @return array<string,mixed> */
    private function data(EntityManagerInterface $em): array
    {
        $count = static fn (string $dql, array $p = []): int => (int) $em->createQuery($dql)->setParameters($p)->getSingleScalarResult();
        $byTheme = static function (string $dql, array $p = []) use ($em): array {
            $out = [];
            foreach ($em->createQuery($dql)->setParameters($p)->getArrayResult() as $r) {
                $out[(string) $r['t']] = ['n' => (int) $r['n'], 's' => (int) ($r['s'] ?? 0)];
            }

            return $out;
        };
        $props = $byTheme('SELECT p.theme AS t, COUNT(p.id) AS n, SUM(p.supportCount) AS s FROM ' . Proposition::class . ' p WHERE p.status = :v AND p.theme IS NOT NULL GROUP BY p.theme', ['v' => Proposition::STATUS_VALIDATED]);
        $debats = $byTheme('SELECT d.theme AS t, COUNT(d.id) AS n FROM ' . Debat::class . ' d WHERE d.status = :s AND d.theme IS NOT NULL GROUP BY d.theme', ['s' => Debat::STATUS_OPEN]);
        $revs = $byTheme('SELECT r.theme AS t, COUNT(r.id) AS n FROM ' . Revendication::class . ' r WHERE r.isPublished = true AND r.theme IS NOT NULL GROUP BY r.theme');
        $themes = [];
        foreach (Proposition::THEMES as $t) {
            $themes[] = ['theme' => $t, 'propositions' => $props[$t]['n'] ?? 0, 'soutiens' => $props[$t]['s'] ?? 0, 'debats' => $debats[$t]['n'] ?? 0, 'revendications' => $revs[$t]['n'] ?? 0];
        }
        usort($themes, static fn ($a, $b) => [$b['soutiens'], $b['propositions']] <=> [$a['soutiens'], $a['propositions']]);

        $revStatus = [];
        foreach ($em->createQuery('SELECT r.status AS st, COUNT(r.id) AS n FROM ' . Revendication::class . ' r WHERE r.isPublished = true GROUP BY r.status')->getArrayResult() as $r) {
            $revStatus[$r['st']] = (int) $r['n'];
        }
        $revRows = [];
        foreach (Revendication::STATUS_LABELS as $k => $label) {
            $revRows[] = ['label' => $label, 'n' => $revStatus[$k] ?? 0];
        }

        $deptQ = static fn (string $class, string $where): array => array_column($em->createQuery("SELECT x.department AS d, COUNT(x.id) AS n FROM $class x WHERE $where AND x.department IS NOT NULL GROUP BY x.department")->setParameters(str_contains($where, ':v') ? ['v' => Proposition::STATUS_VALIDATED] : [])->getArrayResult(), 'n', 'd');
        $dp = $deptQ(Proposition::class, 'x.status = :v');
        $dr = $deptQ(Revendication::class, 'x.isPublished = true');
        $depts = [];
        foreach (array_unique(array_merge(array_keys($dp), array_keys($dr))) as $c) {
            $depts[] = ['code' => (string) $c, 'name' => \App\Service\Departments::name((string) $c) ?? (string) $c, 'propositions' => (int) ($dp[$c] ?? 0), 'revendications' => (int) ($dr[$c] ?? 0)];
        }
        usort($depts, static fn ($a, $b) => ($b['propositions'] + $b['revendications']) <=> ($a['propositions'] + $a['revendications']));

        return [
            'depts' => $depts,
            'key' => [
                'propositions' => $count('SELECT COUNT(p.id) FROM ' . Proposition::class . ' p WHERE p.status = :v', ['v' => Proposition::STATUS_VALIDATED]),
                'soutiens' => (int) $em->createQuery('SELECT COALESCE(SUM(p.supportCount), 0) FROM ' . Proposition::class . ' p WHERE p.status = :v')->setParameter('v', Proposition::STATUS_VALIDATED)->getSingleScalarResult(),
                'debats' => $count('SELECT COUNT(d.id) FROM ' . Debat::class . ' d WHERE d.status = :s', ['s' => Debat::STATUS_OPEN]),
                'revendications' => array_sum($revStatus),
                'dossiers' => $count('SELECT COUNT(x.id) FROM ' . Dossier::class . ' x WHERE x.status = :s', ['s' => Dossier::STATUS_PUBLISHED]),
                'actualites' => $count('SELECT COUNT(n.id) FROM ' . NewsItem::class . ' n WHERE n.status = :s', ['s' => NewsItem::PUBLISHED]),
                'membres' => $count('SELECT COUNT(u.id) FROM ' . User::class . ' u'),
                'institutions' => $count('SELECT COUNT(a.id) FROM ' . ActorContact::class . ' a WHERE a.status = :s', ['s' => ActorContact::ACTIVE]),
            ],
            'themes' => $themes,
            'revStatus' => $revRows,
            'updated' => new \DateTimeImmutable(),
        ];
    }
}
