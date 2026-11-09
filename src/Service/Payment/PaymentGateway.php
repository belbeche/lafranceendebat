<?php

namespace App\Service\Payment;

/** Passerelle de paiement (Stripe Connect en production, faux en test). Montants en centimes d'euro. */
interface PaymentGateway
{
    public function isConfigured(): bool;

    /** Cree un compte Connect Express et renvoie son identifiant. */
    public function createConnectedAccount(string $email, string $country = 'FR'): string;

    /** URL d'onboarding (identite, IBAN) hebergee par Stripe. */
    public function onboardingUrl(string $accountId, string $returnUrl, string $refreshUrl): string;

    /** @return array{charges_enabled:bool,payouts_enabled:bool} */
    public function accountStatus(string $accountId): array;

    /** Cree une session de paiement sur le compte de la plateforme. @return array{id:string,url:string} */
    public function createCheckout(int $orderId, string $title, int $amountCents, string $buyerEmail, string $successUrl, string $cancelUrl): array;

    /** @return array{payment_intent:?string,paid:bool,amount:int} */
    /** Contribution de soutien en ligne : encaisse sur le compte de la plateforme (l'éditeur), sans virement à un tiers. */
    public function createDonationCheckout(int $donationId, int $amountCents, string $donorEmail, string $successUrl, string $cancelUrl): array;

    public function retrieveCheckout(string $sessionId): array;

    /** Verse $amountCents au compte connecte, lie au paiement d'origine. Renvoie l'identifiant du transfert. */
    public function transfer(int $orderId, string $paymentIntentId, string $accountId, int $amountCents): string;

    public function refund(int $orderId, string $paymentIntentId): void;
}
