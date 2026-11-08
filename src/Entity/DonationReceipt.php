<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Justificatif de contribution : sans valeur de reçu fiscal tant que l'éditeur est une micro-entreprise. */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_receipt_number', columns: ['number'])]
class DonationReceipt
{
    public const MODES = ['virement' => 'Virement', 'cheque' => 'Chèque', 'carte' => 'Carte bancaire', 'especes' => 'Espèces', 'autre' => 'Autre'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private string $number;

    #[ORM\Column(length: 160)]
    private string $donorName;

    #[ORM\Column(length: 255)]
    private string $donorAddress;

    #[ORM\Column]
    private int $amountCents;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(length: 20)]
    private string $paymentMode;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    private ?User $issuedBy = null;

    public function __construct(string $number, string $donorName, string $donorAddress, int $amountCents, \DateTimeImmutable $receivedAt, string $paymentMode, ?User $by)
    {
        $this->number = $number;
        $this->donorName = mb_substr(trim($donorName), 0, 160);
        $this->donorAddress = mb_substr(trim($donorAddress), 0, 255);
        $this->amountCents = $amountCents;
        $this->receivedAt = $receivedAt;
        $this->paymentMode = isset(self::MODES[$paymentMode]) ? $paymentMode : 'autre';
        $this->issuedBy = $by;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getNumber(): string { return $this->number; }
    public function getDonorName(): string { return $this->donorName; }
    public function getDonorAddress(): string { return $this->donorAddress; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getReceivedAt(): \DateTimeImmutable { return $this->receivedAt; }
    public function getPaymentMode(): string { return $this->paymentMode; }
    public function getPaymentModeLabel(): string { return self::MODES[$this->paymentMode]; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
