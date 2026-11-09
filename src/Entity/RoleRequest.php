<?php

namespace App\Entity;

use App\Repository\RoleRequestRepository;
use Doctrine\ORM\Mapping as ORM;

/** Candidature a un droit particulier (ex. moderateur). Un administrateur approuve ou refuse. Jamais de role admin. */
#[ORM\Entity(repositoryClass: RoleRequestRepository::class)]
#[ORM\Table(name: 'role_request')]
#[ORM\Index(name: 'idx_role_request_status', columns: ['status'])]
class RoleRequest
{
    public const STATUS_PENDING = 'en_attente';
    public const STATUS_APPROVED = 'approuvee';
    public const STATUS_REJECTED = 'refusee';
    public const STATUS_REVOKED = 'retiree';

    /** Seuls ces droits peuvent etre demandes (liste blanche). ROLE_ADMIN n'y figure jamais. */
    public const ROLES = [
        'ROLE_MODERATOR' => 'Médiateur (relire et modérer les contributions)',
        'ROLE_ACTOR' => 'Acteur public ou prestataire (proposer des services rémunérés)',
        'ROLE_ELU' => 'Élu ou institution (répondre aux propositions des citoyens)',
        'ROLE_ASSOCIATION' => 'Association ou collectif (porter des initiatives citoyennes)',
        'ROLE_JOURNALIST' => 'Journaliste indépendant (publier des tribunes sourcées)',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 40)]
    private string $role;

    #[ORM\Column(type: 'text')]
    private string $motivation;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $organisation = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $proofUrl = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $territory = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $decisionNote = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $decidedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $role, string $motivation)
    {
        $this->user = $user;
        $this->role = $role;
        $this->motivation = $motivation;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOrganisation(): ?string { return $this->organisation; }
    public function getProofUrl(): ?string { return $this->proofUrl; }
    public function getTerritory(): ?string { return $this->territory; }
    public function setDetails(?string $organisation, ?string $proofUrl, ?string $territory): static { $this->organisation = $organisation ?: null; $this->proofUrl = $proofUrl ?: null; $this->territory = $territory ?: null; return $this; }
    public function getUser(): User { return $this->user; }
    public function getRole(): string { return $this->role; }
    public function getRoleLabel(): string { return self::ROLES[$this->role] ?? $this->role; }
    public function getMotivation(): string { return $this->motivation; }
    public function getStatus(): string { return $this->status; }
    public function getDecisionNote(): ?string { return $this->decisionNote; }
    public function getDecidedBy(): ?User { return $this->decidedBy; }
    public function getDecidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }

    public function decide(string $status, User $by, ?string $note): void
    {
        $this->status = $status;
        $this->decidedBy = $by;
        $this->decidedAt = new \DateTimeImmutable();
        $this->decisionNote = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 1000) : null;
    }
}
