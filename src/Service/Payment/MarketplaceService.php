<?php

namespace App\Service\Payment;

use App\Entity\ServiceOffer;
use App\Entity\ServiceOrder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/** Regles de la place de marche : commission, cycle de vie des commandes, reversement apres realisation. */
class MarketplaceService
{
    public const MAX_COMMISSION_BPS = 2000;

    public function __construct(private PaymentGateway $gateway, private EntityManagerInterface $em, private int $commissionBps, private ?\App\Service\NotificationMailer $notifier = null, private bool $open = false)
    {
    }

    public function commissionBps(): int
    {
        return max(0, min(self::MAX_COMMISSION_BPS, $this->commissionBps));
    }

    /** @return array{amount:int,commission:int,net:int} */
    public function breakdown(int $amountCents): array
    {
        $c = (int) round($amountCents * $this->commissionBps() / 10000);

        return ['amount' => $amountCents, 'commission' => $c, 'net' => $amountCents - $c];
    }

    public function canSell(User $u): bool
    {
        // Par defaut : acteurs verifies. Avec MARKETPLACE_OPEN=1 : tout membre dont Stripe a verifie l'identite (KYC) peut etre paye.
        $allowed = $this->open || in_array('ROLE_ACTOR', $u->getRoles(), true);

        return $allowed && !$u->isPaymentBlocked() && $u->isStripeReady() && $u->getStripeAccountId();
    }

    /** Cree la commande puis la session de paiement. Renvoie l'URL de paiement. */
    public function startOrder(ServiceOffer $offer, User $buyer, ?string $note, string $successUrl, string $cancelUrl, bool $noPoliticalUse = false): array
    {
        if (!$offer->isPublished()) {
            throw new \DomainException('Cette prestation n\'est pas disponible.');
        }
        if ($buyer->isRestricted()) {
            throw new \DomainException('Votre compte est restreint (identité à vérifier) : les paiements sont bloqués. Voir la notice « identité réelle ».');
        }
        if ($buyer->needsEmailVerification()) {
            throw new \DomainException('Confirmez votre adresse e-mail (lien reçu à l\'inscription) avant de payer.');
        }
        if ($offer->getProvider()->getId() === $buyer->getId()) {
            throw new \DomainException('Vous ne pouvez pas commander votre propre prestation.');
        }
        if (!$this->canSell($offer->getProvider())) {
            throw new \DomainException('Le prestataire n\'a pas encore activé les paiements.');
        }
        $order = (new ServiceOrder($offer, $buyer, $offer->getPriceCents(), $this->commissionBps()))->setBuyerNote($note);
        if ($noPoliticalUse) {
            $order->declareNoPoliticalUse();
        }
        $this->em->persist($order);
        $this->em->flush();
        $session = $this->gateway->createCheckout((int) $order->getId(), $offer->getTitle(), $order->getAmountCents(), (string) $buyer->getEmail(),
            str_replace('{ID}', (string) $order->getId(), $successUrl), $cancelUrl);
        $order->setStripeSessionId($session['id']);
        $this->em->flush();

        return ['order' => $order, 'url' => $session['url']];
    }

    /** Appele par le webhook : idempotent, ne traite qu'une commande en attente. */
    public function markPaid(string $sessionId, ?string $paymentIntent, int $amountTotal): bool
    {
        $order = $this->em->getRepository(ServiceOrder::class)->findOneBy(['stripeSessionId' => $sessionId]);
        if (!$order || $order->getStatus() !== ServiceOrder::PENDING) {
            return false;
        }
        if ($amountTotal !== $order->getAmountCents()) {
            return false; // montant inattendu : on ne valide jamais.
        }
        $order->setStatus(ServiceOrder::PAID)->setPaidAt(new \DateTimeImmutable())->setStripePaymentIntentId($paymentIntent);
        $this->em->flush();
        $this->notifier?->orderPaid($order);

        return true;
    }

    /** Reverse le net au prestataire. Appele par l'acheteur, par l'administrateur ou apres le delai automatique. */
    public function release(ServiceOrder $order): void
    {
        if ($order->getStatus() !== ServiceOrder::PAID || !$order->getStripePaymentIntentId()) {
            throw new \DomainException('Cette commande ne peut pas être reversée.');
        }
        $account = $order->getOffer()->getProvider()->getStripeAccountId();
        if (!$account) {
            throw new \DomainException('Le prestataire n\'a pas de compte de paiement.');
        }
        // Verrou logique : le statut change AVANT l'appel externe pour eviter un double reversement.
        $order->setStatus(ServiceOrder::COMPLETED)->setCompletedAt(new \DateTimeImmutable());
        $this->em->flush();
        try {
            $order->setStripeTransferId($this->gateway->transfer((int) $order->getId(), $order->getStripePaymentIntentId(), $account, $order->getNetCents()));
        } catch (\Throwable $e) {
            $order->setStatus(ServiceOrder::PAID)->setCompletedAt(null);
            $this->em->flush();
            throw $e;
        }
        $this->em->flush();
        $this->notifier?->orderReleased($order);
    }

    public function refund(ServiceOrder $order): void
    {
        if ($order->getStatus() !== ServiceOrder::PAID || !$order->getStripePaymentIntentId()) {
            throw new \DomainException('Seule une commande payée et non reversée peut être remboursée.');
        }
        $this->gateway->refund((int) $order->getId(), $order->getStripePaymentIntentId());
        $order->setStatus(ServiceOrder::REFUNDED);
        $this->em->flush();
    }
}
