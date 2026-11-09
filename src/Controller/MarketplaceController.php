<?php

namespace App\Controller;

use App\Entity\ServiceOffer;
use App\Entity\ServiceOrder;
use App\Entity\User;
use App\Repository\ServiceOfferRepository;
use App\Repository\ServiceOrderRepository;
use App\Service\Payment\MarketplaceService;
use App\Service\Payment\PaymentGateway;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP "MarketplaceController".
 *
 * Routes :
 *  - GET /services (app_services)
 *  - GET /services/mes-commandes (app_my_orders)
 *  - GET /services/{id<\d+>} (app_service_show)
 *  - POST /services/{id<\d+>}/commander (app_service_order)
 *  - GET /services/commande/{id<\d+>}/retour (app_order_return)
 *  - POST /services/commande/{id<\d+>}/confirmer (app_order_confirm)
 *  - POST /services/commande/{id<\d+>}/rembourser (app_order_refund)
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
#[Route('/services')]
class MarketplaceController extends AbstractController
{
    #[Route('', name: 'app_services', methods: ['GET'])]
    public function index(Request $request, ServiceOfferRepository $repo, MarketplaceService $market): Response
    {
        $cat = (string) $request->query->get('categorie', '');
        $cat = isset(ServiceOffer::CATEGORIES[$cat]) ? $cat : null;

        return $this->render('marketplace/index.html.twig', ['offers' => $repo->published($cat), 'category' => $cat, 'categories' => ServiceOffer::CATEGORIES, 'market' => $market]);
    }

    #[Route('/mes-commandes', name: 'app_my_orders', methods: ['GET'])]
    public function mine(ServiceOrderRepository $orders): Response
    {
        /** @var User $u */
        $u = $this->getUser();

        return $this->render('marketplace/orders.html.twig', ['orders' => $orders->forBuyer($u), 'days' => ServiceOrderRepository::AUTO_RELEASE_DAYS]);
    }

    #[Route('/{id<\d+>}', name: 'app_service_show', methods: ['GET'])]
    public function show(ServiceOffer $offer, MarketplaceService $market): Response
    {
        $u = $this->getUser();
        if (!$offer->isPublished() && !($u && ($u === $offer->getProvider() || $this->isGranted('ROLE_ADMIN')))) {
            throw $this->createNotFoundException();
        }

        return $this->render('marketplace/show.html.twig', ['offer' => $offer, 'split' => $market->breakdown($offer->getPriceCents()), 'bps' => $market->commissionBps(), 'days' => ServiceOrderRepository::AUTO_RELEASE_DAYS]);
    }

    #[Route('/{id<\d+>}/commander', name: 'app_service_order', methods: ['POST'])]
    public function order(ServiceOffer $offer, Request $request, MarketplaceService $market, PaymentGateway $gateway, RateLimiterFactory $orderLimiter, \App\Service\PoliticalGuard $guard): Response
    {
        $back = $this->redirectToRoute('app_service_show', ['id' => $offer->getId()]);
        if (!$this->isCsrfTokenValid('order' . $offer->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée, réessayez.');

            return $back;
        }
        if (!$orderLimiter->create($request->getClientIp() ?? 'x')->consume()->isAccepted()) {
            $this->addFlash('danger', 'Trop de tentatives. Patientez quelques minutes.');

            return $back;
        }
        if (!$request->request->getBoolean('cgv')) {
            $this->addFlash('danger', 'Vous devez accepter les conditions pour payer.');

            return $back;
        }
        if (!$request->request->getBoolean('no_political')) {
            $this->addFlash('danger', 'Vous devez confirmer que cette prestation n\'est destinée ni à un parti, ni à un candidat, ni à une campagne.');

            return $back;
        }
        if ($hit = $guard->find((string) $request->request->get('note', ''))) {
            $this->addFlash('danger', 'Votre message évoque un usage politique (« ' . $hit . ' »). Cette plateforme ne permet pas de rémunérer des services pour un parti, un candidat ou une campagne.');

            return $back;
        }
        if (!$gateway->isConfigured()) {
            $this->addFlash('danger', 'Le paiement n\'est pas encore activé sur la plateforme.');

            return $back;
        }
        /** @var User $buyer */
        $buyer = $this->getUser();
        try {
            $r = $market->startOrder($offer, $buyer, (string) $request->request->get('note', ''),
                str_replace('/0/', '/{ID}/', $this->generateUrl('app_order_return', ['id' => 0], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL)),
                $this->generateUrl('app_service_show', ['id' => $offer->getId()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL), true);
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $back;
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Le paiement est momentanément indisponible. Aucun montant n\'a été débité.');

            return $back;
        }

        return $this->redirect($r['url']);
    }

    #[Route('/commande/{id<\d+>}/retour', name: 'app_order_return', methods: ['GET'])]
    public function back(ServiceOrder $order, MarketplaceService $market, PaymentGateway $gateway): Response
    {
        $this->assertParty($order, false);
        // Le webhook fait foi. Si il n'est pas encore arrive, on interroge Stripe directement (appel serveur a serveur).
        if ($order->getStatus() === ServiceOrder::PENDING && $order->getStripeSessionId()) {
            try {
                $s = $gateway->retrieveCheckout($order->getStripeSessionId());
                if ($s['paid']) {
                    $market->markPaid($order->getStripeSessionId(), $s['payment_intent'], $s['amount']);
                }
            } catch (\Throwable) {
            }
        }

        return $this->render('marketplace/return.html.twig', ['order' => $order, 'days' => ServiceOrderRepository::AUTO_RELEASE_DAYS]);
    }

    #[Route('/commande/{id<\d+>}/confirmer', name: 'app_order_confirm', methods: ['POST'])]
    public function confirm(ServiceOrder $order, Request $request, MarketplaceService $market): Response
    {
        $this->assertParty($order, true);
        if (!$this->isCsrfTokenValid('order_confirm' . $order->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée.');

            return $this->redirectToRoute('app_my_orders');
        }
        try {
            $market->release($order);
            $this->addFlash('success', 'Merci. Le prestataire a été réglé.');
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e instanceof \DomainException ? $e->getMessage() : 'Le reversement a échoué, réessayez plus tard. Rien n\'est perdu.');
        }

        return $this->redirectToRoute('app_my_orders');
    }

    #[Route('/commande/{id<\d+>}/rembourser', name: 'app_order_refund', methods: ['POST'])]
    public function refund(ServiceOrder $order, Request $request, MarketplaceService $market): Response
    {
        $u = $this->getUser();
        if (!($u === $order->getOffer()->getProvider() || $this->isGranted('ROLE_ADMIN'))) {
            throw $this->createAccessDeniedException();
        }
        $to = $this->isGranted('ROLE_ADMIN') && $u !== $order->getOffer()->getProvider() ? 'admin_services' : 'app_actor_space';
        if (!$this->isCsrfTokenValid('order_refund' . $order->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée.');

            return $this->redirectToRoute($to);
        }
        try {
            $market->refund($order);
            $this->addFlash('success', 'Commande remboursée à l\'acheteur.');
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e instanceof \DomainException ? $e->getMessage() : 'Le remboursement a échoué.');
        }

        return $this->redirectToRoute($to);
    }

    private function assertParty(ServiceOrder $order, bool $buyerOnly): void
    {
        $u = $this->getUser();
        $isBuyer = $u === $order->getBuyer();
        if (!$isBuyer && ($buyerOnly || !($u === $order->getOffer()->getProvider() || $this->isGranted('ROLE_ADMIN')))) {
            throw $this->createAccessDeniedException();
        }
    }
}
