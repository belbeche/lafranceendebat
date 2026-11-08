<?php

namespace App\Entity;

use App\Repository\DiscussionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Fil de l'Agora : espace communautaire oriente vers le dialogue, la mediation et la diplomatie.
 * Publication a priori filtree ; les comptes tout neufs passent par une relecture humaine.
 */
#[ORM\Entity(repositoryClass: DiscussionRepository::class)]
#[ORM\Index(name: 'idx_discussion_status_activity', columns: ['status', 'last_activity_at'])]
class Discussion
{
    use \App\Entity\Concern\Editable;

    public const CATEGORIES = [
        'colere' => 'Transformer une colère en proposition',
        'mediation' => 'Médiation entre points de vue',
        'dialogue' => 'Retours d\'expérience de dialogue',
        'institutions' => 'Agir par les voies institutionnelles',
        'entraide' => 'Entraide citoyenne',
        'questions' => 'Questions sur le site',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank(message: 'Donnez un titre à votre fil.')]
    #[Assert\Length(min: 8, max: 160, minMessage: 'Le titre doit faire au moins {{ limit }} caractères.')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Écrivez votre message.')]
    #[Assert\Length(min: 40, max: 4000, minMessage: 'Développez un peu : {{ limit }} caractères minimum.')]
    private ?string $body = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['colere', 'mediation', 'dialogue', 'institutions', 'entraide', 'questions'], message: 'Choisissez une catégorie.')]
    private string $category = 'dialogue';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Event $event = null;

    #[ORM\Column(length: 20)]
    private string $status = Proposition::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $moderationNote = null;

    #[ORM\Column]
    private bool $pinned = false;

    #[ORM\Column]
    private bool $locked = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastActivityAt;

    public function __construct()
    {
        $this->createdAt = $this->lastActivityAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $v): self { $this->title = $v !== null ? trim($v) : null; return $this; }
    public function getBody(): ?string { return $this->body; }
    public function setBody(?string $v): self { $this->body = $v !== null ? trim($v) : null; return $this; }
    public function getCategory(): string { return $this->category; }
    public function setCategory(string $v): self { $this->category = $v; return $this; }
    public function getCategoryLabel(): string { return self::CATEGORIES[$this->category] ?? $this->category; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $v): self { $this->author = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function getEvent(): ?Event { return $this->event; }
    public function setEvent(?Event $e): self { $this->event = $e; return $this; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }
    public function getModerationNote(): ?string { return $this->moderationNote; }
    public function setModerationNote(?string $v): self { $this->moderationNote = $v; return $this; }
    public function isPinned(): bool { return $this->pinned; }
    public function setPinned(bool $v): self { $this->pinned = $v; return $this; }
    public function isLocked(): bool { return $this->locked; }
    public function setLocked(bool $v): self { $this->locked = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getLastActivityAt(): \DateTimeImmutable { return $this->lastActivityAt; }
    public function touch(): self { $this->lastActivityAt = new \DateTimeImmutable(); return $this; }
    public function isVisible(): bool { return $this->status === Proposition::STATUS_VALIDATED; }
    /** Alias pour la file de moderation. */
    public function getContent(): ?string { return $this->body; }
}
