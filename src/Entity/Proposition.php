<?php

namespace App\Entity;

use App\Repository\PropositionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Proposition citoyenne du Registre. Chaque proposition suit la fiche standard
 * (probleme, mesure, autorite competente, cout, financement, delai, indicateur, risques, sources)
 * pour pouvoir etre comparee a celles des candidats avec les memes criteres.
 */
#[ORM\Entity(repositoryClass: PropositionRepository::class)]
#[ORM\Index(name: 'idx_proposition_status_support', columns: ['status', 'support_count'])]
class Proposition
{
    use \App\Entity\Concern\Editable;

    public const STATUS_PENDING = 'En attente';
    public const STATUS_VALIDATED = 'Validée';
    public const STATUS_REJECTED = 'Rejetée';
    /** Retire par son auteur : masque, jamais efface. */
    public const STATUS_WITHDRAWN = 'Retirée';
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_VALIDATED, self::STATUS_REJECTED, self::STATUS_WITHDRAWN];

    public const THEMES = [
        'Finances publiques',
        'Industrie et recherche',
        'Emploi et formation',
        'Pouvoir d\'achat et logement',
        'Santé',
        'École et transmission',
        'Agriculture et alimentation',
        'Justice',
        'Sécurité et réinsertion',
        'Services publics',
        'Retraites et solidarités',
        'Écologie et énergie',
        'Démocratie et dialogue',
        'Défense et résilience',
        'Europe et international',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Donnez un titre clair à votre proposition.')]
    #[Assert\Length(min: 10, max: 180, minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Décrivez la mesure que vous proposez.')]
    #[Assert\Length(min: 50, max: 5000, minMessage: 'Développez un peu : {{ limit }} caractères minimum.')]
    private ?string $description = null;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank(message: 'Choisissez un thème.')]
    #[Assert\Choice(choices: self::THEMES, message: 'Thème inconnu.')]
    private ?string $theme = null;

    /** Département concerné (facultatif) : code officiel, ex. 70, 2A, 974. */
    #[ORM\Column(length: 3, nullable: true)]
    private ?string $department = null;

    /** Dossier de référence (identifiant dans DossierCatalog). */
    #[ORM\Column(length: 190, nullable: true)]
    private ?string $dossierSlug = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Indiquez une adresse web valide (https://...).')]
    #[Assert\Length(max: 255)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private int $supportCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    // --- Fiche standard (facultative mais encouragee) ---

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $problem = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $competentAuthority = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $estimatedCost = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $financing = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $timeline = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $indicator = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $risks = null;

    /** Comment cette proposition favorise le dialogue, la cohesion ou l'apaisement. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1500)]
    private ?string $peaceContribution = null;

    /** Pétition citoyenne consultative : la proposition est ouverte à signature, avec un objectif facultatif. */
    #[ORM\Column(options: ['default' => false])]
    private bool $petition = false;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 10, max: 100000000)]
    private ?int $petitionGoal = null;

    // --- Moderation ---

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $moderationNote = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $moderatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'propositions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Debat $debat = null;

    /** Revendication a laquelle cette proposition repond. */
    #[ORM\ManyToOne(inversedBy: 'propositions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Revendication $revendication = null;

    /** Origine editoriale quand il n'y a pas d'auteur citoyen (ex. document de travail publie). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $origin = null;

    /** Vehicule juridique envisage : loi, amendement, decret, experimentation locale, circulaire... */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $legalVehicle = null;

    /** Texte ou article concerne, avec lien Legifrance si possible. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $legalReference = null;

    /** Garde-fous : ce que la proposition ne doit pas provoquer. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $safeguards = null;

    #[ORM\OneToMany(mappedBy: 'proposition', targetEntity: Amendement::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $amendements;

    #[ORM\OneToMany(mappedBy: 'proposition', targetEntity: Transmission::class)]
    #[ORM\OrderBy(['sentAt' => 'DESC'])]
    private Collection $transmissions;

    #[ORM\OneToMany(mappedBy: 'proposition', targetEntity: Vote::class, orphanRemoval: true)]
    private Collection $votes;

    public function __construct()
    {
        $this->status = self::STATUS_PENDING;
        $this->supportCount = 0;
        $this->createdAt = new \DateTimeImmutable();
        $this->votes = new ArrayCollection();
        $this->amendements = new ArrayCollection();
        $this->transmissions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): self { $this->title = $title !== null ? trim($title) : null; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description !== null ? trim($description) : null; return $this; }

    public function getTheme(): ?string { return $this->theme; }
    public function getDossierSlug(): ?string { return $this->dossierSlug; }
    public function setDossierSlug(?string $v): self { $this->dossierSlug = $v; return $this; }
    public function setTheme(?string $theme): self { $this->theme = $theme; return $this; }
    public function getDepartment(): ?string { return $this->department; }
    public function setDepartment(?string $d): self { $this->department = \App\Service\Departments::valid($d) ? $d : null; return $this; }

    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $sourceUrl): self { $this->sourceUrl = $sourceUrl !== null && trim($sourceUrl) !== '' ? trim($sourceUrl) : null; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Statut de proposition invalide : ' . $status);
        }
        $this->status = $status;

        return $this;
    }

    public function isValidated(): bool { return $this->status === self::STATUS_VALIDATED; }
    public function isPending(): bool { return $this->status === self::STATUS_PENDING; }

    public function isPetition(): bool { return $this->petition; }
    public function setPetition(bool $v): self { $this->petition = $v; if (!$v) { $this->petitionGoal = null; } return $this; }
    public function getPetitionGoal(): ?int { return $this->petitionGoal; }
    public function setPetitionGoal(?int $v): self { $this->petitionGoal = $v; return $this; }
    /** Avancement vers l'objectif, en pourcentage (0 si aucun objectif). */
    public function getPetitionProgress(): int
    {
        return $this->petitionGoal ? (int) min(100, floor($this->supportCount / $this->petitionGoal * 100)) : 0;
    }

    public function getSupportCount(): int { return $this->supportCount; }
    public function setSupportCount(int $supportCount): self { $this->supportCount = max(0, $supportCount); return $this; }
    public function incrementSupport(): self { $this->supportCount++; return $this; }
    public function decrementSupport(): self { $this->supportCount = max(0, $this->supportCount - 1); return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): self { $this->author = $author; return $this; }

    public function getProblem(): ?string { return $this->problem; }
    public function setProblem(?string $v): self { $this->problem = self::clean($v); return $this; }
    public function getCompetentAuthority(): ?string { return $this->competentAuthority; }
    public function setCompetentAuthority(?string $v): self { $this->competentAuthority = self::clean($v); return $this; }
    public function getEstimatedCost(): ?string { return $this->estimatedCost; }
    public function setEstimatedCost(?string $v): self { $this->estimatedCost = self::clean($v); return $this; }
    public function getFinancing(): ?string { return $this->financing; }
    public function setFinancing(?string $v): self { $this->financing = self::clean($v); return $this; }
    public function getTimeline(): ?string { return $this->timeline; }
    public function setTimeline(?string $v): self { $this->timeline = self::clean($v); return $this; }
    public function getIndicator(): ?string { return $this->indicator; }
    public function setIndicator(?string $v): self { $this->indicator = self::clean($v); return $this; }
    public function getRisks(): ?string { return $this->risks; }
    public function setRisks(?string $v): self { $this->risks = self::clean($v); return $this; }
    public function getPeaceContribution(): ?string { return $this->peaceContribution; }
    public function setPeaceContribution(?string $v): self { $this->peaceContribution = self::clean($v); return $this; }

    public function getModerationNote(): ?string { return $this->moderationNote; }
    public function setModerationNote(?string $v): self { $this->moderationNote = self::clean($v); return $this; }
    public function getModeratedAt(): ?\DateTimeImmutable { return $this->moderatedAt; }
    public function setModeratedAt(?\DateTimeImmutable $v): self { $this->moderatedAt = $v; return $this; }

    public function getRevendication(): ?Revendication { return $this->revendication; }
    public function setRevendication(?Revendication $v): self { $this->revendication = $v; return $this; }
    public function getOrigin(): ?string { return $this->origin; }
    public function setOrigin(?string $v): self { $this->origin = self::clean($v); return $this; }
    public function getLegalVehicle(): ?string { return $this->legalVehicle; }
    public function setLegalVehicle(?string $v): self { $this->legalVehicle = self::clean($v); return $this; }
    public function getLegalReference(): ?string { return $this->legalReference; }
    public function setLegalReference(?string $v): self { $this->legalReference = self::clean($v); return $this; }
    public function getSafeguards(): ?string { return $this->safeguards; }
    public function setSafeguards(?string $v): self { $this->safeguards = self::clean($v); return $this; }
    /** @return Collection<int, Amendement> */
    public function getAmendements(): Collection { return $this->amendements; }
    /** @return Collection<int, Transmission> */
    public function getTransmissions(): Collection { return $this->transmissions; }

    public function getDebat(): ?Debat { return $this->debat; }
    public function setDebat(?Debat $debat): self { $this->debat = $debat; return $this; }

    /** @return Collection<int, Vote> */
    public function getVotes(): Collection { return $this->votes; }

    /** Nombre de champs de la fiche standard renseignes (sur 7). */
    public function getSheetCompleteness(): int
    {
        return count(array_filter([
            $this->problem, $this->competentAuthority, $this->estimatedCost,
            $this->financing, $this->timeline, $this->indicator, $this->risks,
        ]));
    }

    public function getAuthorDisplayName(): string
    {
        return $this->author?->getPublicName() ?? ($this->origin ?: 'Citoyen anonymisé');
    }

    private static function clean(?string $v): ?string
    {
        $v = $v !== null ? trim($v) : null;

        return $v === '' ? null : $v;
    }
}
