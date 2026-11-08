<?php

namespace App\Controller;

use App\Entity\ServiceOrder;
use App\Service\Payment\MarketplaceService;
use App\Service\Payment\StripeWebhookVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP « StripeWebhookController ».
 *
 * Routes :
 *  - POST /webhook/stripe (webhook_stripe)
 */
class StripeWebhookController extends AbstractController
{
    #[Route('/webhook/stripe', name: 'webhook_stripe', methods: ['POST'])]
    public function __invoke(Request $request, StripeWebhookVerifier $verifier, MarketplaceService $market, EntityManagerInterface $em, \App\Service\DonationService $donations): Response
    {
        if (!$verifier->isConfigured()) {
            return new Response('', 404);
        }
        $payload = $request->getContent();
        if (strlen($payload) > 200000 || !$verifier->verify($payload, (string) $request->headers->get('Stripe-Signature', ''))) {
            return new Response('invalid signature', 403);
        }
        $event = json_decode($payload, true);
        $obj = $event['data']['object'] ?? [];
        switch ($event['type'] ?? '') {
            case 'checkout.session.completed':
                if (($obj['payment_status'] ?? '') === 'paid' && !empty($obj['id'])) {
                    if (!empty($obj['metadata']['donation_id'])) {
                        $donations->markPaid((string) $obj['id'], (int) ($obj['amount_total'] ?? 0));
                    } else {
                        $market->markPaid((string) $obj['id'], $obj['payment_intent'] ?? null, (int) ($obj['amount_total'] ?? 0));
                    }
                }
                break;
            case 'checkout.session.expired':
                $o = $em->getRepository(ServiceOrder::class)->findOneBy(['stripeSessionId' => (string) ($obj['id'] ?? '')]);
                if ($o && $o->getStatus() === ServiceOrder::PENDING) {
                    $o->setStatus(ServiceOrder::CANCELLED);
                    $em->flush();
                }
                break;
        }

        return new Response('ok');
    }
}
