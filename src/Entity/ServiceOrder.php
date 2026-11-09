<?php

namespace App\Entity;

use App\Repository\ServiceOrderRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Commande d'une prestation. Les montants sont figes a la commande (centimes) :
 * prix = commission plateforme + net verse au prestataire. Les fonds sont reverses apres confirmation de la realisation.
 */
#[ORM\Entity(repositoryClass: ServiceOrderRepository::class)]
#[ORM\Table(name: 'service_order')]
#[ORM\UniqueConstraint(name: 'uniq_service_order_session', columns: ['stripe_session_id'])]
class ServiceOrder
{
    public const PENDING = 'attente_paiement';
    public const PAID = 'payee';
    public const COMPLETED = 'terminee';
    public const REFUNDED = 'remboursee';
    public const CANCELLED = 'annulee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ServiceOffer $offer;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $buyer;

    #[ORM\Column]
    private int $amountCents;

    #[ORM\Column]
    private int $commissionCents;

    #[ORM\Column]
    private int $netCents;

    #[ORM\Column(type: 'smallint')]
    private int $commissionBps;

    #[ORM\Column(length: 20)]
    private string $status = self::PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $buyerNote = null;

    /** Declaration de l'acheteur : la prestation n'est destinee ni a un parti, ni a un candidat, ni a une campagne. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $politicalDeclarationAt = null;

    /** Motif si la moderation a annule la commande pour usage politique. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cancelReason = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $stripeSessionId = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $stripeTransferId = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct(ServiceOffer $offer, User $buyer, int $amountCents, int $commissionBps)
    {
        $this->offer = $offer;
        $this->buyer = $buyer;
        $this->amountCents = $amountCents;
        $this->commissionBps = $commissionBps;
        $this->commissionCents = (int) round($amountCents * $commissionBps / 10000);
        $this->netCents = $amountCents - $this->commissionCents;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function declareNoPoliticalUse(): self { $this->politicalDeclarationAt = new \DateTimeImmutable(); return $this; }
    public function getPoliticalDeclarationAt(): ?\DateTimeImmutable { return $this->politicalDeclarationAt; }
    public function getCancelReason(): ?string { return $this->cancelReason; }
    public function setCancelReason(?string $r): self { $this->cancelReason = $r !== null ? mb_substr($r, 0, 255) : null; return $this; }
    public function getId(): ?int { return $this->id; }
    public function getOffer(): ServiceOffer { return $this->offer; }
    public function getBuyer(): User { return $this->buyer; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getCommissionCents(): int { return $this->commissionCents; }
    public function getNetCents(): int { return $this->netCents; }
    public function getCommissionBps(): int { return $this->commissionBps; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }
    public function getBuyerNote(): ?string { return $this->buyerNote; }
    public function setBuyerNote(?string $n): self { $this->buyerNote = $n !== null && trim($n) !== '' ? mb_substr(trim($n), 0, 1000) : null; return $this; }
    public function getStripeSessionId(): ?string { return $this->stripeSessionId; }
    public function setStripeSessionId(?string $id): self { $this->stripeSessionId = $id; return $this; }
    public function getStripePaymentIntentId(): ?string { return $this->stripePaymentIntentId; }
    public function setStripePaymentIntentId(?string $id): self { $this->stripePaymentIntentId = $id; return $this; }
    public function getStripeTransferId(): ?string { return $this->stripeTransferId; }
    public function setStripeTransferId(?string $id): self { $this->stripeTransferId = $id; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getPaidAt(): ?\DateTimeImmutable { return $this->paidAt; }
    public function setPaidAt(?\DateTimeImmutable $d): self { $this->paidAt = $d; return $this; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeImmutable $d): self { $this->completedAt = $d; return $this; }
}
