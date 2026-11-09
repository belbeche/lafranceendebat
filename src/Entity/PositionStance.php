<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Position d'UNE personne sur UNE affirmation, toujours accompagnée de sa source publique.
 * Une position proposée par l'IA reste invisible tant qu'un modérateur ne l'a pas validée.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_position_stance', columns: ['statement_id', 'candidat_id'])]
class PositionStance
{
    public const PENDING = 'en_attente';
    public const APPROVED = 'validee';
    public const REJECTED = 'rejetee';
    public const LABELS = [2 => 'Tout à fait pour', 1 => 'Plutôt pour', 0 => 'Neutre / partagé', -1 => 'Plutôt contre', -2 => 'Tout à fait contre'];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PositionStatement $statement;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Candidat $candidat;
    #[ORM\Column(type: Types::SMALLINT)]
    private int $stance;
    #[ORM\Column(length: 400)]
    private string $summary;
    #[ORM\Column(length: 500)]
    private string $sourceUrl;
    #[ORM\Column(length: 190)]
    private string $sourceDomain;
    #[ORM\Column]
    private bool $aiProposed = false;
    #[ORM\Column(length: 20)]
    private string $status = self::PENDING;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(PositionStatement $s, Candidat $c, int $stance, string $summary, string $sourceUrl, bool $ai = false)
    {
        $this->statement = $s;
        $this->candidat = $c;
        $this->stance = max(-2, min(2, $stance));
        $this->summary = mb_substr(trim(strip_tags($summary)), 0, 400);
        $this->sourceUrl = mb_substr($sourceUrl, 0, 500);
        $this->sourceDomain = preg_replace('/^www\./', '', (string) parse_url($sourceUrl, PHP_URL_HOST)) ?: '';
        $this->aiProposed = $ai;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getStatement(): PositionStatement { return $this->statement; }
    public function getCandidat(): Candidat { return $this->candidat; }
    public function getStance(): int { return $this->stance; }
    public function setStance(int $v): static { $this->stance = max(-2, min(2, $v)); return $this; }
    public function getStanceLabel(): string { return self::LABELS[$this->stance]; }
    public function getSummary(): string { return $this->summary; }
    public function getSourceUrl(): string { return $this->sourceUrl; }
    public function getSourceDomain(): string { return $this->sourceDomain; }
    public function isAiProposed(): bool { return $this->aiProposed; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): static { $this->status = in_array($s, [self::PENDING, self::APPROVED, self::REJECTED], true) ? $s : self::PENDING; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
