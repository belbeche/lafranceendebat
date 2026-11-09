<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Avis consultatif d'un membre sur une proposition d'un groupe. Un seul avis par membre et par proposition (modifiable).
 * Ce n'est ni un vote officiel ni un sondage représentatif : l'interface le dit. Le commentaire respecte la charte de la paix.
 */
#[ORM\Entity]
#[ORM\Table(name: 'proposal_opinion')]
#[ORM\UniqueConstraint(name: 'uniq_opinion_user_proposal', columns: ['user_id', 'proposal_id'])]
class ProposalOpinion
{
    public const STANCES = ['pour' => 'Plutôt favorable', 'reserve' => 'Je veux en savoir plus', 'contre' => 'Plutôt défavorable'];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PartyProposal $proposal;

    #[ORM\Column(length: 10)]
    private string $stance = 'reserve';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, PartyProposal $proposal)
    {
        $this->user = $user;
        $this->proposal = $proposal;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getUser(): User { return $this->user; }
    public function getProposal(): PartyProposal { return $this->proposal; }
    public function getStance(): string { return $this->stance; }
    public function setStance(string $s): static { $this->stance = isset(self::STANCES[$s]) ? $s : 'reserve'; return $this; }
    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $c): static { $c = trim((string) $c); $this->comment = '' === $c ? null : mb_substr($c, 0, 500); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
