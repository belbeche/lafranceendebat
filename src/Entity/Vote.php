<?php

namespace App\Entity;

use App\Repository\VoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Soutien consultatif a une proposition. Ce n'est PAS un vote electoral :
 * une personne = un soutien par proposition.
 */
#[ORM\Entity(repositoryClass: VoteRepository::class)]
#[ORM\Table(name: 'vote')]
#[ORM\UniqueConstraint(name: 'uniq_vote_proposition_user', columns: ['proposition_id', 'user_id'])]
#[UniqueEntity(fields: ['proposition', 'user'], message: 'Vous soutenez déjà cette proposition.')]
class Vote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'votes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Proposition $proposition = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProposition(): ?Proposition { return $this->proposition; }
    public function setProposition(?Proposition $proposition): self { $this->proposition = $proposition; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
