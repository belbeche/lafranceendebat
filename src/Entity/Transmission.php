<?php

namespace App\Entity;

use App\Repository\TransmissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Suivi d'une transmission a une institution : a qui, par quelle voie legale, quand,
 * et quelle reponse a ete recue. Aucune transmission n'oblige l'institution a agir.
 */
#[ORM\Entity(repositoryClass: TransmissionRepository::class)]
class Transmission
{
    public const STATUS_PREPARED = 'prepared';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_NO_ANSWER = 'no_answer';
    public const STATUS_LABELS = [
        self::STATUS_PREPARED => 'Dossier préparé',
        self::STATUS_SENT => 'Envoyée',
        self::STATUS_ACKNOWLEDGED => 'Accusé de réception',
        self::STATUS_ANSWERED => 'Réponse reçue',
        self::STATUS_NO_ANSWER => 'Sans réponse',
    ];

    /**
     * Voies reellement disponibles. Chaque entree : libelle, ce qui peut se produire, ce qui n'est PAS garanti.
     * Seuils verifies en octobre 2026 sur les sites et textes officiels cites dans la page Voies institutionnelles.
     */
    public const ROUTES = [
        'petition_senat' => 'Pétition au Sénat',
        'petition_assemblee' => 'Pétition à l\'Assemblée nationale',
        'depute_senateur' => 'Contact avec un député ou un sénateur',
        'proposition_loi' => 'Proposition de loi portée par des parlementaires',
        'mission_information' => 'Mission d\'information ou audition',
        'cese' => 'Saisine ou contribution au CESE',
        'rip' => 'Référendum d\'initiative partagée',
        'ice' => 'Initiative citoyenne européenne',
        'collectivite' => 'Collectivité locale (mairie, département, région)',
        'ministere' => 'Courrier à un ministère ou une administration',
        'autre' => 'Autre voie',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'transmissions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Revendication $revendication = null;

    #[ORM\ManyToOne(inversedBy: 'transmissions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Proposition $proposition = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    private ?string $recipient = null;

    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: [
        'petition_senat', 'petition_assemblee', 'depute_senateur', 'proposition_loi', 'mission_information',
        'cese', 'rip', 'ice', 'collectivite', 'ministere', 'autre',
    ])]
    private string $route = 'autre';

    #[ORM\Column(length: 15)]
    private string $status = self::STATUS_PREPARED;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** Contenu du courrier ou de la demande, en clair. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    /** Resume de la reponse officielle recue (jamais reecrit : on cite). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $responseSummary = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url()]
    private ?string $referenceUrl = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getRevendication(): ?Revendication { return $this->revendication; }
    public function setRevendication(?Revendication $v): self { $this->revendication = $v; return $this; }
    public function getProposition(): ?Proposition { return $this->proposition; }
    public function setProposition(?Proposition $v): self { $this->proposition = $v; return $this; }
    public function getRecipient(): ?string { return $this->recipient; }
    public function setRecipient(?string $v): self { $this->recipient = $v; return $this; }
    public function getRoute(): string { return $this->route; }
    public function setRoute(string $v): self { $this->route = isset(self::ROUTES[$v]) ? $v : 'autre'; return $this; }
    public function getRouteLabel(): string { return self::ROUTES[$this->route]; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self
    {
        if (!isset(self::STATUS_LABELS[$v])) {
            throw new \InvalidArgumentException('Statut de transmission invalide.');
        }
        $this->status = $v;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
    public function getStatusLabel(): string { return self::STATUS_LABELS[$this->status]; }
    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function setSentAt(?\DateTimeImmutable $v): self { $this->sentAt = $v; return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $v): self { $this->message = $v; return $this; }
    public function getResponseSummary(): ?string { return $this->responseSummary; }
    public function setResponseSummary(?string $v): self { $this->responseSummary = $v; return $this; }
    public function getReferenceUrl(): ?string { return $this->referenceUrl; }
    public function setReferenceUrl(?string $v): self { $this->referenceUrl = $v; return $this; }

    public function getSubjectTitle(): string
    {
        return $this->revendication?->getTitle() ?? $this->proposition?->getTitle() ?? 'Sans objet';
    }

    public function getDaysWaiting(): ?int
    {
        if (!$this->sentAt || in_array($this->status, [self::STATUS_ANSWERED, self::STATUS_PREPARED], true)) {
            return null;
        }

        return (int) $this->sentAt->diff(new \DateTimeImmutable())->days;
    }
}
