<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Candidature d'un citoyen à une mission, adressée directement à un acteur concerné (élu, association, prestataire). */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_mission_app', columns: ['applicant_id', 'recipient_id', 'mission'])]
class MissionApplication
{
    public const NEW = 'nouvelle';
    public const ACCEPTED = 'acceptee';
    public const DECLINED = 'declinee';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $applicant;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\Column(length: 160)]
    private string $mission;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 30)]
    private string $phone;

    #[ORM\Column(length: 20)]
    private string $status = self::NEW;

    /** true si aucun acteur n'existait pour cette mission : la candidature est alors reçue par l'équipe. */
    #[ORM\Column]
    private bool $fallbackToTeam = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $responseNote = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    public function __construct(User $applicant, User $recipient, string $mission, string $message, string $email, string $phone, bool $fallback = false)
    {
        $this->applicant = $applicant;
        $this->recipient = $recipient;
        $this->mission = $mission;
        $this->message = $message;
        $this->email = $email;
        $this->phone = $phone;
        $this->fallbackToTeam = $fallback;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getApplicant(): User { return $this->applicant; }
    public function getRecipient(): User { return $this->recipient; }
    public function getMission(): string { return $this->mission; }
    public function getMessage(): string { return $this->message; }
    public function getEmail(): string { return $this->email; }
    public function getPhone(): string { return $this->phone; }
    public function getStatus(): string { return $this->status; }
    public function isFallbackToTeam(): bool { return $this->fallbackToTeam; }
    public function getResponseNote(): ?string { return $this->responseNote; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getRespondedAt(): ?\DateTimeImmutable { return $this->respondedAt; }

    public function respond(string $status, ?string $note): void
    {
        $this->status = $status;
        $this->responseNote = $note;
        $this->respondedAt = new \DateTimeImmutable();
    }
}
