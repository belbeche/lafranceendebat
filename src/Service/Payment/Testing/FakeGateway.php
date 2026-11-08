<?php

namespace App\Service\Payment\Testing;

use App\Service\Payment\PaymentGateway;

/** Passerelle factice pour les tests automatiques (jamais utilisee en production). */
class FakeGateway implements PaymentGateway
{
    public static array $calls = [];
    public static bool $failTransfer = false;

    public function isConfigured(): bool { return true; }
    public function createConnectedAccount(string $email, string $country = 'FR'): string { self::$calls[] = 'account'; return 'acct_fake'; }
    public function onboardingUrl(string $accountId, string $returnUrl, string $refreshUrl): string { return 'https://connect.stripe.test/onboard'; }
    public function accountStatus(string $accountId): array { return ['charges_enabled' => true, 'payouts_enabled' => true]; }
    public function createCheckout(int $orderId, string $title, int $amountCents, string $buyerEmail, string $successUrl, string $cancelUrl): array
    {
        self::$calls[] = "checkout:$orderId:$amountCents";
        return ['id' => 'cs_fake_' . $orderId, 'url' => 'https://checkout.stripe.test/pay/' . $orderId];
    }
    public function createDonationCheckout(int $donationId, int $amountCents, string $donorEmail, string $successUrl, string $cancelUrl): array
    {
        self::$calls[] = "donation:$donationId:$amountCents";
        return ['id' => 'cs_don_' . $donationId, 'url' => 'https://checkout.stripe.test/don/' . $donationId];
    }
    public function retrieveCheckout(string $sessionId): array { return ['payment_intent' => 'pi_fake', 'paid' => false, 'amount' => 0]; }
    public function transfer(int $orderId, string $paymentIntentId, string $accountId, int $amountCents): string
    {
        if (self::$failTransfer) { throw new \RuntimeException('boom'); }
        self::$calls[] = "transfer:$orderId:$amountCents:$accountId";
        return 'tr_fake_' . $orderId;
    }
    public function refund(int $orderId, string $paymentIntentId): void { self::$calls[] = "refund:$orderId"; }
}
