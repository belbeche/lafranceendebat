<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Avis motivé d'un acteur (élu, association, prestataire, médiateur) sur une proposition : un avis par acteur et par proposition. */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_endorse_prop_user', columns: ['proposition_id', 'user_id'])]
class ActorEndorsement
{
    public const STANCES = ['approve' => 'Approuve', 'reserve' => 'Approuve avec réserves', 'decline' => 'Ne soutient pas'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Proposition $proposition;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 12)]
    private string $stance = 'approve';

    #[ORM\Column(type: 'text')]
    private string $reason = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Proposition $p, User $u)
    {
        $this->proposition = $p;
        $this->user = $u;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProposition(): Proposition { return $this->proposition; }
    public function getUser(): User { return $this->user; }
    public function getStance(): string { return $this->stance; }
    public function getStanceLabel(): string { return self::STANCES[$this->stance] ?? $this->stance; }
    public function getReason(): string { return $this->reason; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setOpinion(string $stance, string $reason): static { $this->stance = isset(self::STANCES[$stance]) ? $stance : 'reserve'; $this->reason = $reason; $this->createdAt = new \DateTimeImmutable(); return $this; }
}
