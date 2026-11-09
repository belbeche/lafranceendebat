<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Tribune d'un journaliste indépendant : texte signé, sourcé, relu par la modération avant publication. */
#[ORM\Entity]
#[ORM\Index(columns: ['status', 'published_at'])]
class Tribune
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column(length: 190, unique: true)]
    private string $slug;

    #[ORM\Column(length: 300)]
    private string $lead;

    #[ORM\Column(type: Types::TEXT)]
    private string $body;

    /** Une adresse https par ligne. */
    #[ORM\Column(type: Types::TEXT)]
    private string $sources;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $moderationNote = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    public function __construct(User $author, string $title, string $slug, string $lead, string $body, string $sources)
    {
        $this->author = $author;
        $this->title = $title;
        $this->slug = $slug;
        $this->lead = $lead;
        $this->body = $body;
        $this->sources = $sources;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getAuthor(): User { return $this->author; }
    public function getTitle(): string { return $this->title; }
    public function getSlug(): string { return $this->slug; }
    public function getLead(): string { return $this->lead; }
    public function getBody(): string { return $this->body; }
    public function getSources(): string { return $this->sources; }
    /** @return list<string> */
    public function getSourceList(): array { return array_values(array_filter(array_map('trim', preg_split('/\R/', $this->sources) ?: []))); }
    public function getStatus(): string { return $this->status; }
    public function isPublished(): bool { return self::STATUS_PUBLISHED === $this->status; }
    public function getModerationNote(): ?string { return $this->moderationNote; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }

    public function publish(): void
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->moderationNote = null;
        $this->publishedAt = new \DateTimeImmutable();
    }

    /** Repasse le contenu en attente (retrait de la publication par l'administration). */
    public function reopen(): void { $this->status = self::STATUS_PENDING; }

    public function reject(string $note): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->moderationNote = mb_substr($note, 0, 500);
    }
}
