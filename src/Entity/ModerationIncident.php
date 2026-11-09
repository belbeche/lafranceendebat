<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Trace d'un propos bloqué par le filtre : sert à l'équipe de modération et à repérer la récidive. Conservée 12 mois. */
#[ORM\Entity]
#[ORM\Table(name: 'moderation_incident')]
#[ORM\Index(columns: ['created_at'])]
class ModerationIncident
{
    public const NEW = 'nouveau';
    public const HANDLED = 'traite';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user;
    #[ORM\Column(length: 80)]
    private string $context;
    #[ORM\Column(type: 'json')]
    private array $reasons;
    #[ORM\Column(length: 12)]
    private string $severity;
    #[ORM\Column(length: 300)]
    private string $excerpt;
    #[ORM\Column(length: 12)]
    private string $status = self::NEW;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(?User $user, string $context, array $reasons, string $severity, string $excerpt)
    {
        $this->user = $user;
        $this->context = mb_substr($context, 0, 80);
        $this->reasons = array_values($reasons);
        $this->severity = $severity;
        $this->excerpt = mb_substr($excerpt, 0, 300);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function getContext(): string { return $this->context; }
    public function getReasons(): array { return $this->reasons; }
    public function getSeverity(): string { return $this->severity; }
    public function getExcerpt(): string { return $this->excerpt; }
    public function getStatus(): string { return $this->status; }
    public function markHandled(): static { $this->status = self::HANDLED; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
