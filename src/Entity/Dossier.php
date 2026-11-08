<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Dossier de référence proposé par un citoyen : publié seulement après validation par la modération. */
#[ORM\Entity]
#[ORM\Index(columns: ['status', 'published_at'])]
class Dossier
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(length: 160)]
    private string $title = '';

    #[ORM\Column(length: 190, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $theme = null;

    #[ORM\Column(length: 400)]
    private string $intro = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    /** Une adresse https par ligne. */
    #[ORM\Column(type: Types::TEXT)]
    private string $sources = '';

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $moderationNote = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $a): self { $this->author = $a; return $this; }
    public function getAuthorDisplayName(): string { return $this->author?->getPublicName() ?? 'Citoyen'; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): self { $this->title = $v; return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $v): self { $this->slug = $v; return $this; }
    public function getTheme(): ?string { return $this->theme; }
    public function setTheme(?string $v): self { $this->theme = $v; return $this; }
    public function getIntro(): string { return $this->intro; }
    public function setIntro(string $v): self { $this->intro = $v; return $this; }
    public function getBody(): string { return $this->body; }
    public function setBody(string $v): self { $this->body = $v; return $this; }
    public function getSources(): string { return $this->sources; }
    public function setSources(string $v): self { $this->sources = $v; return $this; }

    /** @return list<string> */
    public function getSourceList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $this->sources) ?: []), static fn ($l) => preg_match('#^https://\S+$#i', $l) === 1));
    }

    public function getStatus(): string { return $this->status; }
    public function isPublished(): bool { return self::STATUS_PUBLISHED === $this->status; }
    public function getModerationNote(): ?string { return $this->moderationNote; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }

    public function publish(): self
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->publishedAt = new \DateTimeImmutable();
        $this->moderationNote = null;

        return $this;
    }

    /** Repasse le contenu en attente (retrait de la publication par l'administration). */
    public function reopen(): void { $this->status = self::STATUS_PENDING; }

    public function reject(string $note): self
    {
        $this->status = self::STATUS_REJECTED;
        $this->moderationNote = mb_substr($note, 0, 500);

        return $this;
    }
}
