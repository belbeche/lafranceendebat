<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Preuve de consentement (ou de refus / retrait) : identifiant aleatoire du navigateur, version du texte, choix, date. Aucune adresse IP. */
#[ORM\Entity]
#[ORM\Table(name: 'consent_record')]
#[ORM\Index(name: 'idx_consent_visitor', columns: ['visitor_id'])]
class ConsentRecord
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $visitorId;

    #[ORM\Column]
    private int $version;

    #[ORM\Column]
    private bool $audience;

    /** accept_all | refuse_all | custom | withdraw */
    #[ORM\Column(length: 12)]
    private string $action;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $at;

    public function __construct(string $visitorId, int $version, bool $audience, string $action)
    {
        $this->visitorId = $visitorId;
        $this->version = $version;
        $this->audience = $audience;
        $this->action = $action;
        $this->at = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getVisitorId(): string { return $this->visitorId; }
    public function getVersion(): int { return $this->version; }
    public function isAudience(): bool { return $this->audience; }
    public function getAction(): string { return $this->action; }
    public function getAt(): \DateTimeImmutable { return $this->at; }
}
