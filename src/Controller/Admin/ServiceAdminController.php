<?php

namespace App\Controller\Admin;

use App\Entity\ServiceOffer;
use App\Repository\ServiceOfferRepository;
use App\Repository\ServiceOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur HTTP « ServiceAdminController ».
 *
 * Routes :
 *  - GET /admin/services (admin_services)
 *  - POST /admin/services/{id<\d+>}/retirer-politique (admin_service_withdraw_political)
 *  - POST /admin/services/{id<\d+>}/{decision} (admin_service_decide)
 * Accès : ROLE_ADMIN.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
#[Route('/admin/services')]
#[IsGranted('ROLE_ADMIN')]
class ServiceAdminController extends AbstractController
{
    #[Route('', name: 'admin_services', methods: ['GET'])]
    public function index(ServiceOfferRepository $offers, ServiceOrderRepository $orders): Response
    {
        return $this->render('admin/services/index.html.twig', ['pending' => $offers->pending(), 'orders' => $orders->findBy([], ['createdAt' => 'DESC'], 100), 'published' => $offers->published()]);
    }

    #[Route('/{id<\d+>}/retirer-politique', name: 'admin_service_withdraw_political', methods: ['POST'])]
    public function withdrawPolitical(ServiceOffer $offer, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('service_political' . $offer->getId(), (string) $request->request->get('_token'))) {
            $offer->setStatus(ServiceOffer::STATUS_REJECTED)->setModerationNote('Retirée : usage politique ou campagne électorale interdit par les conditions de la place de marché.');
            $em->flush();
            $this->addFlash('success', 'Prestation retirée (usage politique). Pensez à rembourser les commandes en cours.');
        }

        return $this->redirectToRoute('admin_services');
    }

    #[Route('/{id<\d+>}/{decision}', name: 'admin_service_decide', requirements: ['decision' => 'approve|reject'], methods: ['POST'])]
    public function decide(ServiceOffer $offer, string $decision, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('service_decide' . $offer->getId(), (string) $request->request->get('_token')) && $offer->getStatus() === ServiceOffer::STATUS_PENDING) {
            $offer->setStatus($decision === 'approve' ? ServiceOffer::STATUS_PUBLISHED : ServiceOffer::STATUS_REJECTED)
                ->setModerationNote(mb_substr(trim((string) $request->request->get('note', '')), 0, 500) ?: null);
            $em->flush();
            $this->addFlash('success', $decision === 'approve' ? 'Prestation publiée.' : 'Prestation refusée.');
        }

        return $this->redirectToRoute('admin_services');
    }
}
