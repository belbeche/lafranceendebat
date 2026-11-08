<?php

namespace App\Entity;

use App\Repository\DebatRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un debat suit la methode commune : presentation neutre, faits sources, autorite competente,
 * arguments contradictoires, propositions, puis suites donnees.
 */
#[ORM\Entity(repositoryClass: DebatRepository::class)]
#[UniqueEntity(fields: ['slug'], message: 'Ce lien est déjà utilisé.')]
class Debat
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_OPEN => 'Ouvert',
        self::STATUS_CLOSED => 'Clos',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(length: 190, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Utilisez des minuscules, chiffres et tirets.')]
    private ?string $slug = null;

    #[ORM\Column(length: 60)]
    #[Assert\Length(max: 60)]
    private ?string $theme = null;

    /** Dossier de référence (identifiant dans DossierCatalog). */
    #[ORM\Column(length: 190, nullable: true)]
    private ?string $dossierSlug = null;

    /** Presentation neutre du probleme. */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $summary = null;

    /** Faits verifies, avec sources et dates. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $facts = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $competentAuthority = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $proposedBy = null;

    #[ORM\Column(length: 12)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $closesAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** Compte rendu et suites donnees, publies a la cloture. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $outcome = null;

    #[ORM\OneToMany(mappedBy: 'debat', targetEntity: Argument::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $arguments;

    #[ORM\OneToMany(mappedBy: 'debat', targetEntity: Proposition::class)]
    private Collection $propositions;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->arguments = new ArrayCollection();
        $this->propositions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $v): self { $this->title = $v; return $this; }
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $v): self { $this->slug = $v; return $this; }
    public function getTheme(): ?string { return $this->theme; }
    public function getDossierSlug(): ?string { return $this->dossierSlug; }
    public function setDossierSlug(?string $v): self { $this->dossierSlug = $v; return $this; }
    public function setTheme(?string $v): self { $this->theme = $v; return $this; }
    public function getSummary(): ?string { return $this->summary; }
    public function setSummary(?string $v): self { $this->summary = $v; return $this; }
    public function getFacts(): ?string { return $this->facts; }
    public function setFacts(?string $v): self { $this->facts = $v; return $this; }
    public function getCompetentAuthority(): ?string { return $this->competentAuthority; }
    public function setCompetentAuthority(?string $v): self { $this->competentAuthority = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self
    {
        if (!isset(self::STATUS_LABELS[$v])) {
            throw new \InvalidArgumentException('Statut de débat invalide.');
        }
        $this->status = $v;

        return $this;
    }
    public function getProposedBy(): ?User { return $this->proposedBy; }
    public function setProposedBy(?User $u): self { $this->proposedBy = $u; return $this; }
    public function getStatusLabel(): string { return self::STATUS_LABELS[$this->status]; }
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN && ($this->closesAt === null || $this->closesAt > new \DateTimeImmutable());
    }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getClosesAt(): ?\DateTimeImmutable { return $this->closesAt; }
    public function setClosesAt(?\DateTimeImmutable $v): self { $this->closesAt = $v; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function touch(): self { $this->updatedAt = new \DateTimeImmutable(); return $this; }
    public function getOutcome(): ?string { return $this->outcome; }
    public function setOutcome(?string $v): self { $this->outcome = $v; return $this; }

    /** @return Collection<int, Argument> */
    public function getArguments(): Collection { return $this->arguments; }
    /** @return Collection<int, Proposition> */
    public function getPropositions(): Collection { return $this->propositions; }
}
