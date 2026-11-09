<?php

namespace App\Entity;

use App\Repository\ServiceOfferRepository;
use Doctrine\ORM\Mapping as ORM;

/** Prestation proposee par un acteur verifie (ROLE_ACTOR). Publiee seulement apres relecture. */
#[ORM\Entity(repositoryClass: ServiceOfferRepository::class)]
#[ORM\Table(name: 'service_offer')]
class ServiceOffer
{
    public const STATUS_PENDING = 'En attente';
    public const STATUS_PUBLISHED = 'Publiée';
    public const STATUS_REJECTED = 'Rejetée';
    public const STATUS_WITHDRAWN = 'Retirée';

    public const CATEGORIES = [
        'mediation' => 'Médiation et dialogue',
        'formation' => 'Formation et pédagogie',
        'accompagnement' => 'Accompagnement de projet',
        'juridique' => 'Information juridique et administrative',
        'evenement' => 'Animation de débat ou d\'événement',
        'etude' => 'Étude, diagnostic, évaluation',
    ];

    public const MIN_PRICE_CENTS = 1000; // prix reel obligatoire : pas d'offre symbolique ou gratuite deguisee
    public const MAX_PRICE_CENTS = 500000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $provider;

    #[ORM\Column(length: 120)]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(length: 20)]
    private string $category = 'mediation';

    #[ORM\Column]
    private int $priceCents = 0;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $area = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $moderationNote = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $provider)
    {
        $this->provider = $provider;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProvider(): User { return $this->provider; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $t): self { $this->title = mb_substr(trim($t), 0, 120); return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $d): self { $this->description = trim($d); return $this; }
    public function getCategory(): string { return $this->category; }
    public function getCategoryLabel(): string { return self::CATEGORIES[$this->category] ?? $this->category; }
    public function setCategory(string $c): self { $this->category = $c; return $this; }
    public function getPriceCents(): int { return $this->priceCents; }
    public function setPriceCents(int $p): self { $this->priceCents = $p; return $this; }
    public function getArea(): ?string { return $this->area; }
    public function setArea(?string $a): self { $this->area = $a !== null && trim($a) !== '' ? mb_substr(trim($a), 0, 120) : null; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }
    public function isPublished(): bool { return $this->status === self::STATUS_PUBLISHED; }
    public function getModerationNote(): ?string { return $this->moderationNote; }
    public function setModerationNote(?string $n): self { $this->moderationNote = $n; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
