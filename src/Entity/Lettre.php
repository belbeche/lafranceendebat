<?php

namespace App\Entity;

use App\Repository\LettreRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Lettre d'information envoyee uniquement aux abonnes ayant confirme leur adresse (double opt-in).
 * Texte brut uniquement : aucun HTML saisi, donc aucune injection ni hameçonnage possible par le contenu.
 */
#[ORM\Entity(repositoryClass: LettreRepository::class)]
#[ORM\Table(name: 'lettre')]
class Lettre
{
    public const STATUS_DRAFT = 'brouillon';
    public const STATUS_QUEUED = 'en file';
    public const STATUS_SENDING = 'en cours';
    public const STATUS_SENT = 'envoyee';
    public const STATUS_CANCELLED = 'annulee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank(message: 'Donnez un objet à la lettre.')]
    #[Assert\Length(min: 5, max: 160)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Écrivez le contenu de la lettre.')]
    #[Assert\Length(min: 30, max: 20000)]
    private ?string $body = null;

    public const AUD_SUBSCRIBERS = 'abonnes';
    public const AUD_INVITATION = 'invitation';
    public const AUD_ACTORS = 'acteurs';

    /** abonnes = inscrits confirmes (double opt-in). invitation = premier et unique message a un acteur public (interet legitime, opt-in et desinscription en un clic). acteurs = acteurs publics ayant accepte. */
    #[ORM\Column(length: 20, options: ['default' => 'abonnes'])]
    private string $audience = self::AUD_SUBSCRIBERS;

    /** Ciblage des acteurs publics : categorie (commune, departement...) et departement (code postal). Vide = tous. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $actorCategory = null;
    #[ORM\Column(length: 3, nullable: true)]
    private ?string $actorDept = null;

    /** Langue de la lettre : seuls les destinataires de cette langue la reçoivent. Vide = tous. */
    #[ORM\Column(length: 5, nullable: true)]
    private ?string $locale = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $queuedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    /** Dernier identifiant d'abonne traite : permet de reprendre un envoi interrompu sans doublon. */
    #[ORM\Column(options: ['default' => 0])]
    private int $cursorId = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $sentCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $failedCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $targetCount = 0;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(?string $v): static { $this->subject = $v !== null ? trim(preg_replace('/[\r\n]+/', ' ', $v)) : null; return $this; }
    public function getBody(): ?string { return $this->body; }
    public function setBody(?string $v): static { $this->body = $v; return $this; }
    public function getLocale(): ?string { return $this->locale; }
    public function setLocale(?string $l): static { $this->locale = ($l !== null && in_array($l, \App\Entity\Revendication::LOCALES, true)) ? $l : null; return $this; }
    public function getAudience(): string { return $this->audience; }
    public function setAudience(string $v): static { $this->audience = in_array($v, [self::AUD_ACTORS, self::AUD_INVITATION], true) ? $v : self::AUD_SUBSCRIBERS; return $this; }
    public function getActorCategory(): ?string { return $this->actorCategory; }
    public function getActorDept(): ?string { return $this->actorDept; }
    public function setTarget(?string $category, ?string $dept): static
    {
        $this->actorCategory = ($category && isset(\App\Entity\ActorContact::CATEGORIES[$category])) ? $category : null;
        $this->actorDept = ($dept && preg_match('/^(\d{2}|2A|2B|97\d)$/', $dept)) ? $dept : null;

        return $this;
    }
    public function isForActors(): bool { return $this->audience === self::AUD_ACTORS || $this->audience === self::AUD_INVITATION; }
    public function isInvitation(): bool { return $this->audience === self::AUD_INVITATION; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $u): static { $this->createdBy = $u; return $this; }
    public function getQueuedAt(): ?\DateTimeImmutable { return $this->queuedAt; }
    public function setQueuedAt(?\DateTimeImmutable $v): static { $this->queuedAt = $v; return $this; }
    public function getFinishedAt(): ?\DateTimeImmutable { return $this->finishedAt; }
    public function setFinishedAt(?\DateTimeImmutable $v): static { $this->finishedAt = $v; return $this; }
    public function getCursorId(): int { return $this->cursorId; }
    public function setCursorId(int $v): static { $this->cursorId = $v; return $this; }
    public function getSentCount(): int { return $this->sentCount; }
    public function setSentCount(int $v): static { $this->sentCount = $v; return $this; }
    public function getFailedCount(): int { return $this->failedCount; }
    public function setFailedCount(int $v): static { $this->failedCount = $v; return $this; }
    public function getTargetCount(): int { return $this->targetCount; }
    public function setTargetCount(int $v): static { $this->targetCount = $v; return $this; }
    public function isEditable(): bool { return $this->status === self::STATUS_DRAFT; }
}
