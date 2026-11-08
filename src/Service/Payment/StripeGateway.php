<?php

namespace App\Service\Payment;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Appels directs a l'API REST de Stripe (pas de SDK). Cle secrete et secret de webhook : variables d'environnement uniquement. */
class StripeGateway implements PaymentGateway
{
    private const BASE = 'https://api.stripe.com/v1';

    /** Parametres Checkout fixes depuis Checkout Studio (Dashboard Stripe). Booleens en chaines : http_build_query encode false en 0. */
    private const STUDIO_PARAMS = [
        'ui_mode' => 'hosted_page',
        'billing_address_collection' => 'auto',
        'phone_number_collection' => ['enabled' => 'false'],
        'automatic_tax' => ['enabled' => 'false'],
        'allow_promotion_codes' => 'false',
        'submit_type' => 'auto',
        'integration_identifier' => 'hosted_mobile_app_0001',
        'origin_context' => 'mobile_app',
    ];

    public function __construct(private HttpClientInterface $http, private string $secretKey)
    {
    }

    public function isConfigured(): bool
    {
        return str_starts_with($this->secretKey, 'sk_') || str_starts_with($this->secretKey, 'rk_');
    }

    public function createConnectedAccount(string $email, string $country = 'FR'): string
    {
        return $this->post('/accounts', [
            'type' => 'express', 'country' => $country, 'email' => $email,
            'capabilities' => ['card_payments' => ['requested' => 'true'], 'transfers' => ['requested' => 'true']],
        ], 'acct-' . sha1($email))['id'];
    }

    public function onboardingUrl(string $accountId, string $returnUrl, string $refreshUrl): string
    {
        return $this->post('/account_links', ['account' => $accountId, 'type' => 'account_onboarding', 'return_url' => $returnUrl, 'refresh_url' => $refreshUrl])['url'];
    }

    public function accountStatus(string $accountId): array
    {
        $a = $this->get('/accounts/' . rawurlencode($accountId));

        return ['charges_enabled' => (bool) ($a['charges_enabled'] ?? false), 'payouts_enabled' => (bool) ($a['payouts_enabled'] ?? false)];
    }

    public function createCheckout(int $orderId, string $title, int $amountCents, string $buyerEmail, string $successUrl, string $cancelUrl): array
    {
        $s = $this->post('/checkout/sessions', [
            'mode' => 'payment', 'customer_email' => $buyerEmail, 'client_reference_id' => (string) $orderId,
            'success_url' => $successUrl, 'cancel_url' => $cancelUrl,
            'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => $amountCents, 'product_data' => ['name' => mb_substr($title, 0, 120)]]]],
            'payment_intent_data' => ['transfer_group' => 'ORDER_' . $orderId, 'metadata' => ['order_id' => (string) $orderId]],
            'metadata' => ['order_id' => (string) $orderId],
        ] + self::STUDIO_PARAMS, 'checkout-' . $orderId);

        return ['id' => $s['id'], 'url' => $s['url']];
    }

    public function createDonationCheckout(int $donationId, int $amountCents, string $donorEmail, string $successUrl, string $cancelUrl): array
    {
        $s = $this->post('/checkout/sessions', [
            'mode' => 'payment', 'customer_email' => $donorEmail, 'client_reference_id' => 'DON_' . $donationId,
            'success_url' => $successUrl, 'cancel_url' => $cancelUrl,
            'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => $amountCents, 'product_data' => ['name' => 'Don à l\'association']]]],
            'metadata' => ['donation_id' => (string) $donationId],
        ] + self::STUDIO_PARAMS, 'donation-' . $donationId);

        return ['id' => $s['id'], 'url' => $s['url']];
    }

    public function retrieveCheckout(string $sessionId): array
    {
        $s = $this->get('/checkout/sessions/' . rawurlencode($sessionId));

        return ['payment_intent' => $s['payment_intent'] ?? null, 'paid' => ($s['payment_status'] ?? '') === 'paid', 'amount' => (int) ($s['amount_total'] ?? 0)];
    }

    public function transfer(int $orderId, string $paymentIntentId, string $accountId, int $amountCents): string
    {
        $pi = $this->get('/payment_intents/' . rawurlencode($paymentIntentId));
        $params = ['amount' => $amountCents, 'currency' => 'eur', 'destination' => $accountId, 'transfer_group' => 'ORDER_' . $orderId, 'metadata' => ['order_id' => (string) $orderId]];
        if (!empty($pi['latest_charge'])) {
            $params['source_transaction'] = $pi['latest_charge'];
        }

        return $this->post('/transfers', $params, 'transfer-' . $orderId)['id'];
    }

    public function refund(int $orderId, string $paymentIntentId): void
    {
        $this->post('/refunds', ['payment_intent' => $paymentIntentId, 'metadata' => ['order_id' => (string) $orderId]], 'refund-' . $orderId);
    }

    private function post(string $path, array $body, string $idempotencyKey): array
    {
        return $this->call('POST', $path, ['body' => http_build_query($body, '', '&', PHP_QUERY_RFC3986), 'headers' => ['Idempotency-Key' => $idempotencyKey, 'Content-Type' => 'application/x-www-form-urlencoded']]);
    }

    private function get(string $path): array
    {
        return $this->call('GET', $path, []);
    }

    private function call(string $method, string $path, array $opts): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Stripe n\'est pas configure (STRIPE_SECRET_KEY).');
        }
        $opts['auth_bearer'] = $this->secretKey;
        $opts['timeout'] = 15;
        $resp = $this->http->request($method, self::BASE . $path, $opts);
        $data = $resp->toArray(false);
        if ($resp->getStatusCode() >= 400) {
            throw new \RuntimeException('Stripe : ' . ($data['error']['message'] ?? 'erreur ' . $resp->getStatusCode()));
        }

        return $data;
    }
}
