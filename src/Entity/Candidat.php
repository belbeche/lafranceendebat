<?php

namespace App\Entity;

use App\Repository\CandidatRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Candidat ou elu present sur la plateforme. Aucune note ni classement :
 * la plateforme compare la precision, la faisabilite et la transparence des propositions.
 */
#[ORM\Entity(repositoryClass: CandidatRepository::class)]
#[UniqueEntity(fields: ['slug'], message: 'Ce lien est déjà utilisé.')]
class Candidat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 170, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Utilisez des minuscules, chiffres et tirets.')]
    private ?string $slug = null;

    /** Etiquette politique telle que declaree par la personne elle-meme. */
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $declaredLabel = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $biography = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url()]
    private ?string $programUrl = null;

    /** Accepte un debat public contradictoire et de repondre aux questions des citoyens. */
    #[ORM\Column]
    private bool $acceptsPublicDebate = false;

    #[ORM\Column]
    private bool $isPublished = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'candidat', targetEntity: Engagement::class, orphanRemoval: true)]
    #[ORM\OrderBy(['theme' => 'ASC', 'id' => 'ASC'])]
    private Collection $engagements;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->engagements = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): self { $this->name = $v; return $this; }
    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $v): self { $this->slug = $v; return $this; }
    public function getDeclaredLabel(): ?string { return $this->declaredLabel; }
    public function setDeclaredLabel(?string $v): self { $this->declaredLabel = $v; return $this; }
    public function getRole(): ?string { return $this->role; }
    public function setRole(?string $v): self { $this->role = $v; return $this; }
    public function getBiography(): ?string { return $this->biography; }
    public function setBiography(?string $v): self { $this->biography = $v; return $this; }
    public function getProgramUrl(): ?string { return $this->programUrl; }
    public function setProgramUrl(?string $v): self { $this->programUrl = $v; return $this; }
    public function acceptsPublicDebate(): bool { return $this->acceptsPublicDebate; }
    public function setAcceptsPublicDebate(bool $v): self { $this->acceptsPublicDebate = $v; return $this; }
    public function isPublished(): bool { return $this->isPublished; }
    public function setIsPublished(bool $v): self { $this->isPublished = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, Engagement> */
    public function getEngagements(): Collection { return $this->engagements; }

    public function __toString(): string { return (string) $this->name; }
}
