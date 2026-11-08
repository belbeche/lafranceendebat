<?php

namespace App\Entity;

use App\Repository\AmendementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Amendement citoyen : une amelioration precise d'une proposition, soumise a moderation puis publiee. */
#[ORM\Entity(repositoryClass: AmendementRepository::class)]
class Amendement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'amendements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Proposition $proposition = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    /** Texte propose en remplacement ou en ajout. */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Écrivez la modification que vous proposez.')]
    #[Assert\Length(min: 20, max: 3000, minMessage: 'Soyez plus précis : {{ limit }} caractères minimum.')]
    private ?string $content = null;

    /** Pourquoi cette modification ameliore la proposition (faits, source). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $justification = null;

    #[ORM\Column(length: 20)]
    private string $status = Proposition::STATUS_PENDING;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProposition(): ?Proposition { return $this->proposition; }
    public function setProposition(?Proposition $v): self { $this->proposition = $v; return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $v): self { $this->author = $v; return $this; }
    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $v): self { $this->content = $v !== null ? trim($v) : null; return $this; }
    public function getJustification(): ?string { return $this->justification; }
    public function setJustification(?string $v): self { $this->justification = $v !== null && trim($v) !== '' ? trim($v) : null; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }
    public function isVisible(): bool { return $this->status === Proposition::STATUS_VALIDATED; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getAuthorDisplayName(): string { return $this->author?->getPublicName() ?? 'Citoyen anonymisé'; }
}
