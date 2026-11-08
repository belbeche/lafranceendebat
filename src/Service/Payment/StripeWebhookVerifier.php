<?php

namespace App\Service\Payment;

/** Verifie l'en-tete Stripe-Signature (HMAC SHA-256, tolerance 5 minutes). */
class StripeWebhookVerifier
{
    public function __construct(private string $secret)
    {
    }

    public function isConfigured(): bool
    {
        return str_starts_with($this->secret, 'whsec_');
    }

    public function verify(string $payload, string $header, ?int $now = null): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        $t = null;
        $sigs = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $t = (int) $v;
            } elseif ($k === 'v1') {
                $sigs[] = $v;
            }
        }
        if (!$t || !$sigs || abs(($now ?? time()) - $t) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $t . '.' . $payload, $this->secret);
        foreach ($sigs as $s) {
            if (hash_equals($expected, $s)) {
                return true;
            }
        }

        return false;
    }
}
