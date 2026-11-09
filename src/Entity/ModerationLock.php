<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Prise en charge d'un contenu par un moderateur (verrou de 15 min) et trace des decisions : sert a la charge de l'equipe. */
#[ORM\Entity]
#[ORM\Table(name: 'moderation_lock')]
#[ORM\Index(columns: ['kind', 'target_id'])]
class ModerationLock
{
    public const MINUTES = 15;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 30)]
    private string $kind;
    #[ORM\Column(name: 'target_id')]
    private int $targetId;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;
    #[ORM\Column]
    private \DateTimeImmutable $takenAt;
    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    public function __construct(string $kind, int $targetId, User $user)
    {
        $this->kind = $kind;
        $this->targetId = $targetId;
        $this->user = $user;
        $this->takenAt = new \DateTimeImmutable();
        $this->expiresAt = $this->takenAt->modify('+' . self::MINUTES . ' minutes');
    }

    public function getId(): ?int { return $this->id; }
    public function getKind(): string { return $this->kind; }
    public function getTargetId(): int { return $this->targetId; }
    public function getUser(): User { return $this->user; }
    public function getTakenAt(): \DateTimeImmutable { return $this->takenAt; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function getDecidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }
    public function decide(): static { $this->decidedAt = new \DateTimeImmutable(); return $this; }
    public function isActive(): bool { return null === $this->decidedAt && $this->expiresAt > new \DateTimeImmutable(); }
}
