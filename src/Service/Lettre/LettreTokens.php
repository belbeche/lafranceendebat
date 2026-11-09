<?php

namespace App\Service\Lettre;

use App\Entity\NewsletterSubscriber;

/**
 * Jetons signes (HMAC-SHA256 avec le secret de l'application) : rien n'est stocke en base,
 * ils ne peuvent pas etre devines ni fabriques, et sont lies a l'identifiant ET a l'adresse.
 */
class LettreTokens
{
    public function __construct(private readonly string $secret)
    {
    }

    public function unsubscribe(NewsletterSubscriber $s): string
    {
        return $this->mac('unsub|' . $s->getId() . '|' . strtolower((string) $s->getEmail()));
    }

    public function actorUnsubscribe(int $id, string $email): string
    {
        return $this->mac('actor-unsub|' . $id . '|' . strtolower($email));
    }

    public function actorConsent(int $id, string $email): string
    {
        return $this->mac('actor-consent|' . $id . '|' . strtolower($email));
    }

    public function confirm(NewsletterSubscriber $s, int $expires): string
    {
        return $expires . '.' . $this->mac('confirm|' . $s->getId() . '|' . strtolower((string) $s->getEmail()) . '|' . $expires);
    }

    public function isValidUnsubscribe(NewsletterSubscriber $s, string $token): bool
    {
        return hash_equals($this->unsubscribe($s), $token);
    }

    public function isValidConfirm(NewsletterSubscriber $s, string $token): bool
    {
        $parts = explode('.', $token, 2);
        if (2 !== count($parts) || !ctype_digit($parts[0]) || (int) $parts[0] < time()) {
            return false;
        }

        return hash_equals($this->confirm($s, (int) $parts[0]), $token);
    }

    private function mac(string $data): string
    {
        return substr(hash_hmac('sha256', $data, $this->secret), 0, 40);
    }
}
