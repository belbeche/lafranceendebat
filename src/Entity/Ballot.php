<?php

namespace App\Entity;

use App\Repository\BallotRepository;
use Doctrine\ORM\Mapping as ORM;

/** Un bulletin consultatif. Une personne = un bulletin par scrutin (contrainte en base). */
#[ORM\Entity(repositoryClass: BallotRepository::class)]
#[ORM\Table(name: 'ballot')]
#[ORM\UniqueConstraint(name: 'uniq_ballot_consultation_user', columns: ['consultation_id', 'user_id'])]
class Ballot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Consultation $consultation;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'smallint')]
    private int $choice;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Consultation $c, User $u, int $choice)
    {
        $this->consultation = $c;
        $this->user = $u;
        $this->choice = $choice;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getChoice(): int { return $this->choice; }
    public function getUser(): User { return $this->user; }
    public function getConsultation(): Consultation { return $this->consultation; }
}
