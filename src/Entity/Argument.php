<?php

namespace App\Entity;

use App\Repository\ArgumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contribution a un debat. Pour repondre a un autre argument, l'auteur peut d'abord reformuler
 * ce qu'il a compris de la position adverse : une pratique reconnue de dialogue apaise.
 */
#[ORM\Entity(repositoryClass: ArgumentRepository::class)]
#[ORM\Index(name: 'idx_argument_debat_status', columns: ['debat_id', 'status'])]
class Argument
{
    public const STANCE_FOR = 'pour';
    public const STANCE_AGAINST = 'contre';
    public const STANCE_NUANCE = 'nuance';
    public const STANCES = [
        self::STANCE_FOR => 'Pour',
        self::STANCE_AGAINST => 'Contre',
        self::STANCE_NUANCE => 'Nuancé',
    ];

    public const STATUS_PENDING = Proposition::STATUS_PENDING;
    public const STATUS_VALIDATED = Proposition::STATUS_VALIDATED;
    public const STATUS_REJECTED = Proposition::STATUS_REJECTED;
    /** Retire par son auteur : masque, jamais efface (les reponses et l'historique restent coherents). */
    public const STATUS_WITHDRAWN = 'Retirée';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'arguments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Debat $debat = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: [self::STANCE_FOR, self::STANCE_AGAINST, self::STANCE_NUANCE], message: 'Choisissez une position.')]
    private string $stance = self::STANCE_NUANCE;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Écrivez votre argument.')]
    #[Assert\Length(min: 30, max: 6000, minMessage: 'Développez un peu : {{ limit }} caractères minimum.')]
    private ?string $content = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: 'Indiquez une adresse web valide (https://...).')]
    private ?string $sourceUrl = null;

    /** "Ce que je comprends de votre position" : facultatif, affiche avant la reponse. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $reformulation = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Argument $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $replies;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_VALIDATED;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $editCount = 0;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->replies = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getDebat(): ?Debat { return $this->debat; }
    public function setDebat(?Debat $v): self { $this->debat = $v; return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $v): self { $this->author = $v; return $this; }
    public function getStance(): string { return $this->stance; }
    public function setStance(string $v): self { $this->stance = $v; return $this; }
    public function getStanceLabel(): string { return self::STANCES[$this->stance] ?? $this->stance; }
    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $v): self { $this->content = $v !== null ? trim($v) : null; return $this; }
    public function getSourceUrl(): ?string { return $this->sourceUrl; }
    public function setSourceUrl(?string $v): self { $this->sourceUrl = $v !== null && trim($v) !== '' ? trim($v) : null; return $this; }
    public function getReformulation(): ?string { return $this->reformulation; }
    public function setReformulation(?string $v): self { $this->reformulation = $v !== null && trim($v) !== '' ? trim($v) : null; return $this; }
    public function getParent(): ?Argument { return $this->parent; }
    public function setParent(?Argument $v): self { $this->parent = $v; return $this; }
    /** @return Collection<int, Argument> */
    public function getReplies(): Collection { return $this->replies; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function getEditCount(): int { return $this->editCount; }
    /** Delai de grace : une correction dans les 5 minutes suivant la publication n'est pas comptee comme modification. */
    public function markEdited(): self
    {
        if ($this->createdAt < new \DateTimeImmutable('-5 minutes')) {
            $this->updatedAt = new \DateTimeImmutable();
            ++$this->editCount;
        }

        return $this;
    }
    public function withdraw(): self { $this->status = self::STATUS_WITHDRAWN; return $this; }
    public function isVisible(): bool { return $this->status === self::STATUS_VALIDATED; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getAuthorDisplayName(): string { return $this->author?->getPublicName() ?? 'Citoyen anonymisé'; }
}
