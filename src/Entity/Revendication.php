<?php

namespace App\Entity;

use App\Repository\RevendicationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Registre des revendications : ce qui a ete denonce ou demande publiquement par un groupe
 * de la population, avec sa source, les propositions qui y repondent et les suites donnees.
 * Le but est qu'une revendication ne disparaisse pas faute de reponse.
 */
#[ORM\Entity(repositoryClass: RevendicationRepository::class)]
#[UniqueEntity(fields: ['slug'], message: 'Ce lien est déjà utilisé.')]
class Revendication
{
    use \App\Entity\Concern\Editable;

    public const STATUS_RAISED = 'raised';
    public const STATUS_TRANSMITTED = 'transmitted';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_ADDRESSED = 'addressed';
    public const STATUS_NO_FOLLOW_UP = 'no_follow_up';
    public const STATUS_LABELS = [
        self::STATUS_RAISED => 'Portée, pas encore transmise',
        self::STATUS_TRANSMITTED => 'Transmise, en attente de réponse',
        self::STATUS_ANSWERED => 'Réponse reçue',
        self::STATUS_ADDRESSED => 'Prise en compte',
        self::STATUS_NO_FOLLOW_UP => 'Restée sans suite',
    ];

    public const GROUPS = [
        'Jeunes et étudiants',
        'Enseignants et éducation',
        'Salariés et syndicats',
        'Soignants et santé',
        'Agriculteurs',
        'Retraités',
        'Sapeurs-pompiers et secours',
        'Forces de sécurité',
        'Justice et personnes détenues',
        'Associations et économie sociale et solidaire',
        'Habitants et territoires',
        'Contribuables',
        'Entreprises et indépendants',
        'Élus locaux et collectivités',
        'Tous les citoyens',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public const LOCALES = ['fr','en','es','de','it','pt','ar','tr','ru','zh','hi','ja'];

    #[ORM\Column(length: 5, options: ['default' => 'fr'])]
    private string $locale = 'fr';

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(length: 210, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Utilisez des minuscules, chiffres et tirets.')]
    private ?string $slug = null;

    /** Qui porte cette revendication. */
    #[ORM\Column(length: 80)]
    #[Assert\Choice(choices: self::GROUPS)]
    private ?string $groupLabel = null;

    #[ORM\Column(length: 60)]
    #[Assert\Choice(choices: Proposition::THEMES)]
    private ?string $theme = null;

    /** Ce qui est denonce ou demande, en termes factuels. */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $summary = null;

    /** Ce que la source dit exactement (chiffres, demandes) et ce qui reste a verifier. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $verificationNote = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceLabel = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url()]
    private ?string $sourceUrl = null;

    /** Autorite qui peut repondre. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $competentAuthority = null;

    /** Voie juridique envisageable (loi, decret, experimentation, amendement...). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $legalPath = null;

    #[ORM\Column(length: 15)]
    private string $status = self::STATUS_RAISED;

    #[ORM\Column]
    private bool $isPublished = true;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $submittedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $raisedAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'revendication', targetEntity: Proposition::class)]
    private Collection $propositions;

    #[ORM\OneToMany(mappedBy: 'revendication', targetEntity: Transmission::class)]
    #[ORM\OrderBy(['sentAt' => 'DESC'])]
    private Collection $transmissions;

    public function __construct()
    {
        $this->raisedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->propositions = new ArrayCollection();
        $this->transmissions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $v): self { $this->title = $v; return $this; }
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $v): self { $this->slug = $v; return $this; }
    public function getGroupLabel(): ?string { return $this->groupLabel; }
    public function setGroupLabel(?string $v): self { $this->groupLabel = $v; return $this; }
    public function getTheme(): ?string { return $this->theme; }
    public function setTheme(?string $v): self { $this->theme = $v; return $this; }
    public function getSummary(): ?string { return $this->summary; }
    public function setSummary(?string $v): self { $this->summary = $v; return $this; }
    public function getVerificationNote(): ?string { return $this->verificationNote; }
    public function setVerificationNote(?string $v): self { $this->verificationNote = $v; return $this; }
    public function getSourceLabel(): ?string { return $this->sourceLabel; }
    public function setSourceLabel(?string $v): self { $this->sourceLabel = $v; return $this; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $v): self { $this->sourceUrl = $v; return $this; }
    public function getCompetentAuthority(): ?string { return $this->competentAuthority; }
    public function setCompetentAuthority(?string $v): self { $this->competentAuthority = $v; return $this; }
    public function getLegalPath(): ?string { return $this->legalPath; }
    public function setLegalPath(?string $v): self { $this->legalPath = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self
    {
        if (!isset(self::STATUS_LABELS[$v])) {
            throw new \InvalidArgumentException('Statut de revendication invalide.');
        }
        $this->status = $v;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
    public function getStatusLabel(): string { return self::STATUS_LABELS[$this->status]; }
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $moderationNote = null;

    public function getModerationNote(): ?string { return $this->moderationNote; }
    public function setModerationNote(?string $n): self { $this->moderationNote = $n; return $this; }
    public function isPublished(): bool { return $this->isPublished; }
    public function getSubmittedBy(): ?User { return $this->submittedBy; }
    public function setSubmittedBy(?User $u): self { $this->submittedBy = $u; return $this; }
    public function setIsPublished(bool $v): self { $this->isPublished = $v; return $this; }
    public function getRaisedAt(): \DateTimeImmutable { return $this->raisedAt; }
    public function setRaisedAt(\DateTimeImmutable $v): self { $this->raisedAt = $v; return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $v): self { $this->updatedAt = $v; return $this; }

    /** @return Collection<int, Proposition> */
    public function getPropositions(): Collection { return $this->propositions; }
    /** @return Collection<int, Transmission> */
    public function getTransmissions(): Collection { return $this->transmissions; }

    /** Revendication encore sans reponse : transmise ou non, aucune reponse recue. */
    public function isAwaitingAnswer(): bool
    {
        return in_array($this->status, [self::STATUS_RAISED, self::STATUS_TRANSMITTED, self::STATUS_NO_FOLLOW_UP], true);
    }

    /** Nombre de jours depuis la derniere mise a jour (pour afficher "sans reponse depuis..."). */
    public function getDaysSinceUpdate(): int
    {
        return (int) $this->updatedAt->diff(new \DateTimeImmutable())->days;
    }

    public function __toString(): string { return (string) $this->title; }

    public function getLocale(): string { return $this->locale; }
    public function setLocale(string $l): static { $this->locale = in_array($l, self::LOCALES, true) ? $l : 'fr'; return $this; }
}
