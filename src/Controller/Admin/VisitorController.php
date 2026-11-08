<?php
// src/Controller/Admin/VisitorController.php

namespace App\Controller\Admin;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\SiteVisitorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur HTTP « VisitorController ».
 *
 * Routes :
 *  - GET /admin/visitors/ (admin_visitors)
 *  - GET /admin/visitors/stats (admin_visitors_stats)
 *  - GET /admin/visitors/export (admin_visitors_export)
 *  - GET /admin/visitors/cleanup (admin_visitors_cleanup)
 *  - GET /admin/visitors/admin/visitor/{id} (admin_visitor_show)
 *  - POST /admin/visitors/admin/visitor/delete/{id} (admin_visitor_delete)
 *  - POST /admin/visitors/admin/visitors/block-ip (admin_visitors_block_ip)
 * Accès : ROLE_ADMIN.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
#[Route('/admin/visitors')]
#[IsGranted('ROLE_ADMIN')]
class VisitorController extends AbstractController
{
    #[Route('/', name: 'admin_visitors')]
    public function index(SiteVisitorRepository $visitorRepository): Response
    {
        $visitors = $visitorRepository->findBy([], ['visitedAt' => 'DESC'], 100);
        
        $stats = [
            'today' => $visitorRepository->getTodayVisitorsCount(),
            'yesterday' => $visitorRepository->getVisitorsCountSince(
                new \DateTimeImmutable('yesterday'),
                new \DateTimeImmutable('today')
            ),
            'last_week' => $visitorRepository->getVisitorsCountSince(new \DateTimeImmutable('-7 days')),
            'last_month' => $visitorRepository->getVisitorsCountSince(new \DateTimeImmutable('-30 days')),
            'total' => $visitorRepository->getTotalUniqueVisitors(),
        ];

        return $this->render('admin/visitors/index.html.twig', [
            'visitors' => $visitors,
            'stats' => $stats,
            'popular_pages' => $visitorRepository->getPopularPages(),
            'visitors_by_country' => $visitorRepository->getVisitorsByCountry(),
        ]);
    }

    #[Route('/stats', name: 'admin_visitors_stats')]
    public function stats(SiteVisitorRepository $visitorRepository): JsonResponse
    {
        $data = [
            'last_30_days' => $visitorRepository->getVisitorsLast30Days(),
            'popular_pages' => $visitorRepository->getPopularPages(),
            'by_country' => $visitorRepository->getVisitorsByCountry(),
            'hourly_today' => $visitorRepository->getHourlyStatsForToday(),
        ];

        return new JsonResponse($data);
    }

    #[Route('/export', name: 'admin_visitors_export')]
    public function export(SiteVisitorRepository $visitorRepository): Response
    {
        $visitors = $visitorRepository->findBy([], ['visitedAt' => 'DESC']);

        $csvData = "Date;Heure;IP;Pays;Ville;Page;User Agent\n";
        
        foreach ($visitors as $visitor) {
            $csvData .= sprintf(
                "%s;%s;%s;%s;%s;%s;%s\n",
                $visitor->getVisitedAt()->format('d/m/Y'),
                $visitor->getVisitedAt()->format('H:i:s'),
                $visitor->getIpAddress(),
                $visitor->getCountryCode() ?? 'N/A',
                $visitor->getCity() ?? 'N/A',
                $visitor->getPageVisited() ?? 'N/A',
                substr($visitor->getUserAgent() ?? '', 0, 100)
            );
        }

        $response = new Response($csvData);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="visiteurs_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    #[Route('/cleanup', name: 'admin_visitors_cleanup')]
    public function cleanup(SiteVisitorRepository $visitorRepository): Response
    {
        $deletedCount = $visitorRepository->cleanupOldVisits(90);
        
        $this->addFlash('success', "{$deletedCount} anciennes visites ont été supprimées.");

        return $this->redirectToRoute('admin_visitors');
    }

    #[Route('/admin/visitor/{id}', name: 'admin_visitor_show')]
    public function show(int $id, SiteVisitorRepository $visitorRepository): Response
    {
        $visitor = $visitorRepository->find($id);
        
        if (!$visitor) {
            throw $this->createNotFoundException('Visiteur non trouvé');
        }
        
        // Récupérer l'historique des visites pour cette IP
        $visitorHistory = $visitorRepository->findBy(
            ['ipAddress' => $visitor->getIpAddress()],
            ['visitedAt' => 'DESC']
        );
        
        return $this->render('admin/visitors/show.html.twig', [
            'visitor' => $visitor,
            'visitorHistory' => $visitorHistory
        ]);
    }

    #[Route('/admin/visitor/delete/{id}', name: 'admin_visitor_delete', methods: ['POST'])]
    public function delete(int $id, SiteVisitorRepository $visitorRepository, EntityManagerInterface $entityManager, Request $request): Response
    {
        $visitor = $visitorRepository->find($id);
        
        if (!$visitor) {
            throw $this->createNotFoundException('Visiteur non trouvé');
        }
        
        if ($this->isCsrfTokenValid('delete' . $visitor->getId(), $request->request->get('_token'))) {
            $entityManager->remove($visitor);
            $entityManager->flush();
            $this->addFlash('success', 'Visite supprimée avec succès.');
        }
        
        return $this->redirectToRoute('admin_visitors');
    }

    #[Route('/admin/visitors/block-ip', name: 'admin_visitors_block_ip', methods: ['POST'])]
    public function blockIp(
        Request $request, 
        BlockedIpRepository $blockedIpRepository,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $ip = $data['ip'] ?? null;
        $reason = $data['reason'] ?? 'Bloqué manuellement depuis l\'interface admin';
        $duration = $data['duration'] ?? null; // null = permanent, sinon en heures

        if (!$ip) {
            return $this->json(['success' => false, 'error' => 'IP non fournie']);
        }

        try {
            // Bloquer l'IP
            $blocked = $blockedIpRepository->block(
                $ip, 
                $reason, 
                $duration,
                $this->getUser()->getUserIdentifier()
            );

            // Logger l'action
            $logger->info('IP bloquée', [
                'ip' => $ip,
                'by' => $this->getUser()->getUserIdentifier(),
                'duration' => $duration ?: 'permanent'
            ]);

            // Optionnel : supprimer toutes les visites de cette IP
            $visitorRepository = $entityManager->getRepository(SiteVisitor::class);
            $visitorRepository->deleteByIp($ip);

            return $this->json([
                'success' => true, 
                'message' => "IP {$ip} bloquée avec succès",
                'blocked' => [
                    'ip' => $blocked->getIpAddress(),
                    'expires' => $blocked->getExpiresAt()?->format('d/m/Y H:i'),
                    'permanent' => $blocked->getExpiresAt() === null
                ]
            ]);

        } catch (\Exception $e) {
            $logger->error('Erreur lors du blocage IP', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'success' => false, 
                'error' => 'Erreur lors du blocage: ' . $e->getMessage()
            ], 500);
        }
    }
}