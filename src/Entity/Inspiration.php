<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Personne qui a contribué ou innové : fiche sourcée, publiée après relecture de la modération. */
#[ORM\Entity]
#[ORM\Index(columns: ['status', 'domain'])]
class Inspiration
{
    public const PENDING = 'pending';
    public const PUBLISHED = 'published';
    public const REJECTED = 'rejected';
    public const DOMAINS = [
        'democratie' => 'Démocratie et biens communs',
        'droits' => 'Droits et justice',
        'social' => 'Solidarité et protection sociale',
        'sante' => 'Santé',
        'sciences' => 'Sciences',
        'education' => 'Éducation',
        'numerique' => 'Numérique',
        'economie' => 'Économie et entrepreneuriat',
        'ecologie' => 'Écologie',
        'reconstruction' => 'Reconstruction et Europe',
        'paix' => 'Paix et médiation',
        'autre' => 'Autre',
    ];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 20)]
    private string $domain;

    #[ORM\Column(type: Types::TEXT)]
    private string $summary;

    #[ORM\Column(length: 400)]
    private string $sourceUrl;

    #[ORM\Column(length: 12)]
    private string $status = self::PENDING;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $moderationNote = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Une avancée par ligne, rédigée par la modération à partir des sources. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $advances = null;

    public function __construct(string $name, string $domain, string $summary, string $sourceUrl, ?User $author = null)
    {
        $this->name = $name;
        $this->domain = isset(self::DOMAINS[$domain]) ? $domain : 'autre';
        $this->summary = $summary;
        $this->sourceUrl = $sourceUrl;
        $this->author = $author;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getAdvancesText(): ?string { return $this->advances; }
    /** @return list<string> */
    public function getAdvances(): array { return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->advances) ?: []))); }
    public function setAdvances(?string $text): static { $t = trim((string) $text); $this->advances = '' === $t ? null : mb_substr($t, 0, 3000); return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function getName(): string { return $this->name; }
    public function getDomain(): string { return $this->domain; }
    public function getDomainLabel(): string { return self::DOMAINS[$this->domain]; }
    public function getSummary(): string { return $this->summary; }
    public function getSourceUrl(): string { return $this->sourceUrl; }
    public function getSourceHost(): string { return (string) preg_replace('/^www\./', '', (string) parse_url($this->sourceUrl, PHP_URL_HOST)); }
    public function getStatus(): string { return $this->status; }
    public function getModerationNote(): ?string { return $this->moderationNote; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function publish(): void { $this->status = self::PUBLISHED; $this->moderationNote = null; }
    /** Repasse le contenu en attente (retrait de la publication par l'administration). */
    public function reopen(): void { $this->status = self::PENDING; }

    public function reject(string $note): void { $this->status = self::REJECTED; $this->moderationNote = mb_substr($note, 0, 500); }
}
