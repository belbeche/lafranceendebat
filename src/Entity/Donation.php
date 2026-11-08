<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Contribution de soutien en ligne (carte bancaire via Stripe) à une micro-entreprise : justificatif de paiement sans valeur fiscale, émis une fois le paiement confirmé. */
#[ORM\Entity]
#[ORM\Table(name: 'online_donation')]
#[ORM\UniqueConstraint(name: 'uniq_donation_token', columns: ['token'])]
class Donation
{
    public const PENDING = 'attente_paiement';
    public const PAID = 'payee';
    public const CANCELLED = 'annulee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $token;

    #[ORM\Column(length: 160)]
    private string $donorName;

    #[ORM\Column(length: 255)]
    private string $donorAddress;

    #[ORM\Column(length: 180)]
    private string $donorEmail;

    #[ORM\Column]
    private int $amountCents;

    #[ORM\Column(length: 20)]
    private string $status = self::PENDING;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $stripeSessionId = null;

    #[ORM\ManyToOne]
    private ?DonationReceipt $receipt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, string $address, string $email, int $amountCents)
    {
        $this->token = bin2hex(random_bytes(24));
        $this->donorName = mb_substr(trim($name), 0, 160);
        $this->donorAddress = mb_substr(trim($address), 0, 255);
        $this->donorEmail = mb_substr(trim($email), 0, 180);
        $this->amountCents = $amountCents;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getToken(): string { return $this->token; }
    public function getDonorName(): string { return $this->donorName; }
    public function getDonorAddress(): string { return $this->donorAddress; }
    public function getDonorEmail(): string { return $this->donorEmail; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }
    public function getStripeSessionId(): ?string { return $this->stripeSessionId; }
    public function setStripeSessionId(?string $id): self { $this->stripeSessionId = $id; return $this; }
    public function getReceipt(): ?DonationReceipt { return $this->receipt; }
    public function setReceipt(?DonationReceipt $r): self { $this->receipt = $r; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
