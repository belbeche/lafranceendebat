<?php

namespace App\Entity;

use App\Repository\ContentTranslationRepository;
use Doctrine\ORM\Mapping as ORM;

/** Traduction d'un contenu (revendication, proposition, fil, service) dans une langue. Automatique ou proposée par un contributeur et relue. */
#[ORM\Entity(repositoryClass: ContentTranslationRepository::class)]
#[ORM\Table(name: 'content_translation')]
#[ORM\UniqueConstraint(name: 'uniq_content_translation', columns: ['type', 'ref_id', 'locale'])]
class ContentTranslation
{
    public const SRC_AUTO = 'auto';
    public const SRC_USER = 'user';
    public const PENDING = 'pending';
    public const PUBLISHED = 'published';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private string $type;

    #[ORM\Column(name: 'ref_id')]
    private int $refId;

    #[ORM\Column(length: 5)]
    private string $locale;

    /** @var array<string,string> */
    #[ORM\Column(type: 'json')]
    private array $fields = [];

    #[ORM\Column(length: 10)]
    private string $source = self::SRC_AUTO;

    #[ORM\Column(length: 10)]
    private string $status = self::PUBLISHED;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $type, int $refId, string $locale, array $fields, string $source = self::SRC_AUTO, string $status = self::PUBLISHED, ?User $author = null)
    {
        $this->type = $type;
        $this->refId = $refId;
        $this->locale = $locale;
        $this->fields = $fields;
        $this->source = $source;
        $this->status = $status;
        $this->author = $author;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getType(): string { return $this->type; }
    public function getRefId(): int { return $this->refId; }
    public function getLocale(): string { return $this->locale; }
    public function getFields(): array { return $this->fields; }
    public function getSource(): string { return $this->source; }
    public function getStatus(): string { return $this->status; }
    public function isPublished(): bool { return $this->status === self::PUBLISHED; }
    public function publish(): static { $this->status = self::PUBLISHED; return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
