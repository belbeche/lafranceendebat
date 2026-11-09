<?php

namespace App\Entity;

use App\Repository\EngagementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Engagement d'un candidat ou d'un elu, presente avec la meme fiche standard que les
 * propositions citoyennes, puis suivi dans le temps (annonce, en cours, tenu, abandonne).
 */
#[ORM\Entity(repositoryClass: EngagementRepository::class)]
#[ORM\Index(name: 'idx_engagement_theme', columns: ['theme'])]
class Engagement
{
    public const STATUS_ANNOUNCED = 'announced';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_KEPT = 'kept';
    public const STATUS_ABANDONED = 'abandoned';
    public const STATUS_LABELS = [
        self::STATUS_ANNOUNCED => 'Annoncé',
        self::STATUS_IN_PROGRESS => 'En cours',
        self::STATUS_KEPT => 'Tenu',
        self::STATUS_ABANDONED => 'Abandonné',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'engagements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Candidat $candidat = null;

    #[ORM\Column(length: 60)]
    #[Assert\Choice(choices: Proposition::THEMES)]
    private ?string $theme = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $problem = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $measure = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $competentAuthority = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $announcedCost = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $financing = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $timeline = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $indicator = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $risks = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $objectionsReply = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url()]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 15)]
    private string $status = self::STATUS_ANNOUNCED;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCandidat(): ?Candidat { return $this->candidat; }
    public function setCandidat(?Candidat $v): self { $this->candidat = $v; return $this; }
    public function getTheme(): ?string { return $this->theme; }
    public function setTheme(?string $v): self { $this->theme = $v; return $this; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $v): self { $this->title = $v; return $this; }
    public function getProblem(): ?string { return $this->problem; }
    public function setProblem(?string $v): self { $this->problem = $v; return $this; }
    public function getMeasure(): ?string { return $this->measure; }
    public function setMeasure(?string $v): self { $this->measure = $v; return $this; }
    public function getCompetentAuthority(): ?string { return $this->competentAuthority; }
    public function setCompetentAuthority(?string $v): self { $this->competentAuthority = $v; return $this; }
    public function getAnnouncedCost(): ?string { return $this->announcedCost; }
    public function setAnnouncedCost(?string $v): self { $this->announcedCost = $v; return $this; }
    public function getFinancing(): ?string { return $this->financing; }
    public function setFinancing(?string $v): self { $this->financing = $v; return $this; }
    public function getTimeline(): ?string { return $this->timeline; }
    public function setTimeline(?string $v): self { $this->timeline = $v; return $this; }
    public function getIndicator(): ?string { return $this->indicator; }
    public function setIndicator(?string $v): self { $this->indicator = $v; return $this; }
    public function getRisks(): ?string { return $this->risks; }
    public function setRisks(?string $v): self { $this->risks = $v; return $this; }
    public function getObjectionsReply(): ?string { return $this->objectionsReply; }
    public function setObjectionsReply(?string $v): self { $this->objectionsReply = $v; return $this; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $v): self { $this->sourceUrl = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self
    {
        if (!isset(self::STATUS_LABELS[$v])) {
            throw new \InvalidArgumentException('Statut d\'engagement invalide.');
        }
        $this->status = $v;

        return $this;
    }
    public function getStatusLabel(): string { return self::STATUS_LABELS[$this->status]; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $v): self { $this->updatedAt = $v; return $this; }

    /** Nombre de champs de la fiche renseignes sur 9 : mesure de transparence, pas de qualite politique. */
    public function getSheetCompleteness(): int
    {
        return count(array_filter([
            $this->problem, $this->measure, $this->competentAuthority, $this->announcedCost, $this->financing,
            $this->timeline, $this->indicator, $this->risks, $this->sourceUrl,
        ], fn ($v) => $v !== null && trim((string) $v) !== ''));
    }
}
