<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Annonce de mission écrite par un citoyen : une DEMANDE (j'ai besoin d'aide) ou une OFFRE (je propose mon aide). Publiée après modération. */
#[ORM\Entity]
#[ORM\Index(columns: ['status', 'kind', 'created_at'])]
class MissionPost
{
    public const DEMANDE = 'demande';
    public const OFFRE = 'offre';
    public const PENDING = 'pending';
    public const PUBLISHED = 'published';
    public const REJECTED = 'rejected';
    public const CLOSED = 'closed';
    public const PAY = ['free' => 'Bénévole', 'paid' => 'Rémunérée', 'both' => 'Bénévole ou rémunérée'];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(length: 10)]
    private string $kind;

    #[ORM\Column(length: 120)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $territory = null;

    #[ORM\Column(length: 10)]
    private string $pay = 'free';

    #[ORM\Column(length: 12)]
    private string $status = self::PENDING;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $moderationNote = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $author, string $kind, string $title, string $description, ?string $territory, string $pay)
    {
        $this->author = $author;
        $this->kind = self::OFFRE === $kind ? self::OFFRE : self::DEMANDE;
        $this->title = $title;
        $this->description = $description;
        $this->territory = $territory ?: null;
        $this->pay = isset(self::PAY[$pay]) ? $pay : 'free';
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getAuthor(): User { return $this->author; }
    public function getKind(): string { return $this->kind; }
    public function isDemande(): bool { return self::DEMANDE === $this->kind; }
    public function getKindLabel(): string { return $this->isDemande() ? "Besoin d'aide" : "Propose son aide"; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): string { return $this->description; }
    public function getTerritory(): ?string { return $this->territory; }
    public function getPay(): string { return $this->pay; }
    public function getPayLabel(): string { return self::PAY[$this->pay]; }
    public function getStatus(): string { return $this->status; }
    public function getModerationNote(): ?string { return $this->moderationNote; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function publish(): void { $this->status = self::PUBLISHED; $this->moderationNote = null; }
    /** Repasse le contenu en attente (retrait de la publication par l'administration). */
    public function reopen(): void { $this->status = self::PENDING; }

    public function reject(string $note): void { $this->status = self::REJECTED; $this->moderationNote = mb_substr($note, 0, 500); }
    public function close(): void { $this->status = self::CLOSED; }
}
