<?php

namespace App\Entity;

use App\Repository\SignalementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Signalement d'un contenu par un participant. Un meme participant ne peut signaler
 * qu'une fois un meme contenu. Trois signalements distincts remettent le contenu en revue.
 */
#[ORM\Entity(repositoryClass: SignalementRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_signalement_reporter_target', columns: ['reporter_id', 'target_type', 'target_id'])]
#[ORM\Index(name: 'idx_signalement_target', columns: ['target_type', 'target_id'])]
class Signalement
{
    public const TARGET_PROPOSITION = 'proposition';
    public const TARGET_ARGUMENT = 'argument';
    public const TARGET_DISCUSSION = 'discussion';
    public const TARGET_REPLY = 'reponse';
    public const TARGETS = [self::TARGET_PROPOSITION, self::TARGET_ARGUMENT, self::TARGET_DISCUSSION, self::TARGET_REPLY];

    public const REASONS = [
        'insulte' => 'Insulte ou attaque personnelle',
        'menace' => 'Menace ou appel à la violence',
        'harcelement' => 'Harcèlement ou intimidation',
        'discrimination' => 'Propos discriminatoires',
        'donnees_personnelles' => 'Données personnelles divulguées',
        'desinformation' => 'Information fausse présentée comme un fait',
        'autre' => 'Autre motif',
    ];

    public const STATUS_OPEN = 'open';
    public const STATUS_HANDLED = 'handled';
    public const STATUS_DISMISSED = 'dismissed';

    public const AUTO_REVIEW_THRESHOLD = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $targetType;

    #[ORM\Column]
    private int $targetId;

    #[ORM\Column(length: 30)]
    private string $reason = 'autre';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reporter = null;

    #[ORM\Column(length: 12)]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $handledAt = null;

    public function __construct(string $targetType, int $targetId)
    {
        if (!in_array($targetType, self::TARGETS, true)) {
            throw new \InvalidArgumentException('Type de contenu signalé inconnu.');
        }
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTargetType(): string { return $this->targetType; }
    public function getTargetId(): int { return $this->targetId; }
    public function getReason(): string { return $this->reason; }
    public function setReason(string $v): self { $this->reason = isset(self::REASONS[$v]) ? $v : 'autre'; return $this; }
    public function getReasonLabel(): string { return self::REASONS[$this->reason]; }
    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $v): self { $this->details = $v !== null && trim($v) !== '' ? trim($v) : null; return $this; }
    public function getReporter(): ?User { return $this->reporter; }
    public function setReporter(?User $v): self { $this->reporter = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self
    {
        $this->status = $v;
        $this->handledAt = $v === self::STATUS_OPEN ? null : new \DateTimeImmutable();

        return $this;
    }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getHandledAt(): ?\DateTimeImmutable { return $this->handledAt; }
}
