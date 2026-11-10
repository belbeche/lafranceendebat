<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Actualité du moment, écrite ou importée par l'éditeur depuis l'administration.
 * Brouillon par défaut : une actualité n'est visible qu'une fois publiée, et seulement avec une source https.
 * Jamais de texte rédigé par une IA publié sans relecture : l'éditeur reste l'auteur.
 */
#[ORM\Entity]
#[ORM\Table(name: 'news_item')]
#[ORM\Index(columns: ['status', 'published_on'])]
class NewsItem
{
    public const DRAFT = 'brouillon';
    public const PUBLISHED = 'publiee';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $title = '';

    #[ORM\Column(length: 190, unique: true)]
    private string $slug = '';

    /** Résumé factuel, 600 caractères au maximum (affiché dans la liste et sur l'accueil). */
    #[ORM\Column(length: 600)]
    private string $summary = '';

    /** Texte complet, mis en forme avec le mini-langage de SafeMarkdown. Facultatif. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $body = null;

    #[ORM\Column(length: 160)]
    private string $sourceName = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $publishedOn;

    #[ORM\Column(length: 20)]
    private string $status = self::DRAFT;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->publishedOn = new \DateTimeImmutable('today');
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = mb_substr(trim(strip_tags($v)), 0, 180); return $this; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $v): static { $this->slug = mb_substr($v, 0, 190); return $this; }
    public function getSummary(): string { return $this->summary; }
    public function setSummary(string $v): static { $this->summary = mb_substr(trim(strip_tags($v)), 0, 600); return $this; }
    public function getBody(): ?string { return $this->body; }
    public function setBody(?string $v): static { $v = trim((string) $v); $this->body = '' === $v ? null : mb_substr($v, 0, 20000); return $this; }
    public function getSourceName(): string { return $this->sourceName; }
    public function setSourceName(string $v): static { $this->sourceName = mb_substr(trim(strip_tags($v)), 0, 160); return $this; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $v): static
    {
        $v = trim((string) $v);
        $this->sourceUrl = ('' !== $v && preg_match('~^https://[^\s]+\.[^\s]+$~i', $v)) ? mb_substr($v, 0, 255) : null;

        return $this;
    }
    public function getPublishedOn(): \DateTimeImmutable { return $this->publishedOn; }
    public function setPublishedOn(\DateTimeImmutable $d): static { $this->publishedOn = $d; return $this; }
    public function getStatus(): string { return $this->status; }
    public function isPublished(): bool { return self::PUBLISHED === $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** Raison pour laquelle l'actualité ne peut pas être publiée, ou null si elle peut l'être. */
    public function publishProblem(): ?string
    {
        if (mb_strlen($this->title) < 8) {
            return 'Titre trop court.';
        }
        if (mb_strlen($this->summary) < 40) {
            return 'Résumé trop court (40 caractères minimum).';
        }
        if ('' === $this->sourceName) {
            return 'Indiquez le nom de la source.';
        }
        if (!$this->sourceUrl) {
            return 'Ajoutez le lien https de la source : sans lien vérifiable, une actualité ne se publie pas.';
        }

        return null;
    }

    /** Publie si possible. Retourne le message d'erreur sinon. */
    public function publish(): ?string
    {
        if ($p = $this->publishProblem()) {
            return $p;
        }
        $this->status = self::PUBLISHED;

        return null;
    }

    public function unpublish(): static { $this->status = self::DRAFT; return $this; }
}
