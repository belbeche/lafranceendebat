<?php

namespace App\Service;

use App\Entity\Donation;
use App\Service\Payment\PaymentGateway;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Contribution de soutien en ligne : création de la session de paiement Stripe, puis, une fois payée,
 * confirmation par e-mail. Le reçu fiscal PDF n'est émis que si les reçus sont activés (organisme éligible) ;
 * pour une micro-entreprise ils restent désactivés (DONATION_RECEIPTS_ENABLED=0).
 * La page est ouverte par DONATIONS_OPEN=1, indépendamment des reçus (utile aussi pour tester en mode test Stripe).
 */
class DonationService
{
    public const MIN_CENTS = 500;
    public const MAX_CENTS = 1000000;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PaymentGateway $gateway,
        private readonly DonationReceiptService $receipts,
        private readonly TransactionalMailer $mailer,
        private readonly Security\DisposableEmailPolicy $emailPolicy,
        private readonly UrlGeneratorInterface $urls,
        private readonly bool $open = false,
    ) {
    }

    public function isOpen(): bool
    {
        return $this->open && $this->gateway->isConfigured();
    }

    /** Vrai si un reçu fiscal PDF doit être émis (organisme éligible uniquement). */
    public function issuesReceipts(): bool
    {
        return $this->receipts->isEnabled();
    }

    /** @return array{donation:Donation,url:string} */
    public function start(string $name, string $address, string $email, int $cents, string $successUrl, string $cancelUrl): array
    {
        if (!$this->isOpen()) {
            throw new \DomainException('Les contributions en ligne ne sont pas ouvertes.');
        }
        if ($cents < self::MIN_CENTS || $cents > self::MAX_CENTS) {
            throw new \DomainException(sprintf('La contribution doit être compris entre %d et %d euros.', self::MIN_CENTS / 100, self::MAX_CENTS / 100));
        }
        $needsAddress = $this->issuesReceipts();
        if (mb_strlen(trim($name)) < 3 || ($needsAddress && mb_strlen(trim($address)) < 8) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException($needsAddress ? 'Nom, adresse complète et e-mail valides obligatoires pour établir le justificatif.' : 'Nom et e-mail valides obligatoires pour confirmer votre contribution.');
        }
        if ($this->emailPolicy->isSuspicious($email)) {
            throw new \DomainException('Utilisez une adresse e-mail durable : le justificatif ne peut pas être envoyé à une adresse jetable.');
        }
        $d = new Donation($name, $address, $email, $cents);
        $this->em->persist($d);
        $this->em->flush();
        $s = $this->gateway->createDonationCheckout((int) $d->getId(), $cents, $d->getDonorEmail(), str_replace('{TOKEN}', $d->getToken(), $successUrl), $cancelUrl);
        $d->setStripeSessionId($s['id']);
        $this->em->flush();

        return ['donation' => $d, 'url' => $s['url']];
    }

    /** Idempotent : appelé par le webhook ET au retour du donateur. */
    public function markPaid(string $sessionId, int $amountTotal): bool
    {
        $d = $this->em->getRepository(Donation::class)->findOneBy(['stripeSessionId' => $sessionId]);
        if (!$d || $d->getStatus() !== Donation::PENDING || $amountTotal !== $d->getAmountCents()) {
            return false;
        }
        $d->setStatus(Donation::PAID);
        if ($this->issuesReceipts()) {
            $receipt = $this->receipts->issue($d->getDonorName(), $d->getDonorAddress(), $d->getAmountCents(), new \DateTimeImmutable(), 'carte', null);
            $d->setReceipt($receipt);
            $this->em->flush();
            $this->mailer->donationReceipt($d->getDonorEmail(), $d->getDonorName(), $receipt->getNumber(), $this->receipts->pdf($receipt));
        } else {
            $this->em->flush();
            $this->mailer->contributionThanks($d->getDonorEmail(), $d->getDonorName(), $d->getAmountCents(), $this->pageUrl($d));
        }

        return true;
    }

    /** Adresse personnelle (jeton de 48 caractères) de la page de suivi d'une contribution. */
    public function pageUrl(Donation $d): string
    {
        return $this->urls->generate('app_donate_thanks', ['token' => $d->getToken()], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function find(string $token): ?Donation
    {
        return preg_match('/^[a-f0-9]{48}$/', $token) ? $this->em->getRepository(Donation::class)->findOneBy(['token' => $token]) : null;
    }

    public function sync(Donation $d): void
    {
        if ($d->getStatus() === Donation::PENDING && $d->getStripeSessionId()) {
            try {
                $s = $this->gateway->retrieveCheckout($d->getStripeSessionId());
                if ($s['paid']) {
                    $this->markPaid($d->getStripeSessionId(), $s['amount']);
                }
            } catch (\Throwable) {
            }
        }
    }
}
