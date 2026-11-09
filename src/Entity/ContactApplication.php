<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Candidature à une mission transmise par e-mail à un contact public importé (avec désinscription en un clic). */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_contact_app', columns: ['applicant_id', 'contact_id', 'mission'])]
class ContactApplication
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $applicant;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ActorContact $contact;

    #[ORM\Column(length: 160)]
    private string $mission;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $applicant, ActorContact $contact, string $mission, string $message)
    {
        $this->applicant = $applicant;
        $this->contact = $contact;
        $this->mission = $mission;
        $this->message = $message;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getApplicant(): User { return $this->applicant; }
    public function getContact(): ActorContact { return $this->contact; }
    public function getMission(): string { return $this->mission; }
    public function getMessage(): string { return $this->message; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
