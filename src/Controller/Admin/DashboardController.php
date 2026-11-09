<?php

namespace App\Controller\Admin;

use App\Entity\Argument;
use App\Entity\Debat;
use App\Entity\Proposition;
use App\Entity\Signalement;
use App\Repository\ArgumentRepository;
use App\Repository\DebatRepository;
use App\Repository\PropositionRepository;
use App\Repository\SignalementRepository;
use App\Repository\SiteVisitorRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Tableau de bord et statistiques de l'administration (remplace l'ancien AdminController, retire du routage). */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(
        PropositionRepository $propositions,
        ArgumentRepository $arguments,
        SignalementRepository $signalements,
        DebatRepository $debats,
        UserRepository $users,
        SiteVisitorRepository $visitors,
        \Doctrine\ORM\EntityManagerInterface $em,
        \App\Service\Backup\DatabaseBackup $backup,
        \App\Service\Approval\ApprovalRegistry $approvals,
    ): Response {
        return $this->render('admin/dashboard/index.html.twig', [
            'backupState' => $backup->state(),
            'backupProblems' => $backup->problems(),
            'approvalsTotal' => $approvals->total(),
            'mediationOpen' => $signalements->count(['status' => Signalement::STATUS_OPEN]),
            'actorsPending' => $em->getRepository(\App\Entity\ActorContact::class)->count(['status' => 'actif']),
            'missionsPending' => $em->getRepository(\App\Entity\MissionPost::class)->count(['status' => 'pending']),
            'pendingPropositions' => $propositions->findPending(8),
            'pendingPropositionsCount' => $propositions->count(['status' => Proposition::STATUS_PENDING]),
            'validatedCount' => $propositions->count(['status' => Proposition::STATUS_VALIDATED]),
            'pendingArguments' => $arguments->findPending(8),
            'pendingArgumentsCount' => $arguments->count(['status' => Argument::STATUS_PENDING]),
            'openReports' => $signalements->findOpen(8),
            'openReportsCount' => $signalements->count(['status' => Signalement::STATUS_OPEN]),
            'draftDebatsCount' => $debats->count(['status' => Debat::STATUS_DRAFT]),
            'pendingTribunesCount' => $em->getRepository(\App\Entity\Tribune::class)->count(['status' => \App\Entity\Tribune::STATUS_PENDING]),
            'pendingRolesCount' => $em->getRepository(\App\Entity\RoleRequest::class)->count(['status' => \App\Entity\RoleRequest::STATUS_PENDING]),
            'openDebatsCount' => $debats->count(['status' => Debat::STATUS_OPEN]),
            'totalUsers' => $users->count([]),
            'todayVisitors' => $visitors->getTodayVisitorsCount(),
            'lastWeekVisitors' => $visitors->getVisitorsCountSince(new \DateTimeImmutable('-7 days')),
            'lastMonthVisitors' => $visitors->getVisitorsCountSince(new \DateTimeImmutable('-30 days')),
            'popularPages' => $visitors->getPopularPages(8),
        ]);
    }

    #[Route('/statistics', name: 'admin_statistics', methods: ['GET'])]
    public function statistics(\App\Service\AdminStats $stats): Response
    {
        return $this->render('admin/statistics/index.html.twig', ['s' => $stats->all(), 'page_title' => 'Statistiques de la plateforme']);
    }

    #[Route('/statistics.csv', name: 'admin_statistics_csv', methods: ['GET'])]
    public function statisticsCsv(\App\Service\AdminStats $stats): Response
    {
        $flat = [];
        $walk = static function (array $a, string $prefix) use (&$walk, &$flat): void {
            foreach ($a as $k => $v) {
                is_array($v) ? $walk($v, $prefix . $k . '.') : $flat[] = [$prefix . $k, $v];
            }
        };
        $walk($stats->all(), '');
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Indicateur', 'Valeur'], ';');
        foreach ($flat as $row) {
            fputcsv($out, array_map(static fn ($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v, $row), ';');
        }
        rewind($out);

        return new Response((string) stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="statistiques-' . date('Y-m-d') . '.csv"']);
    }
}
