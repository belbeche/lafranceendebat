<?php

namespace App\Entity;

use Doctrine\ORM\Mapping\Id;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Entity;
use App\Repository\EventRepository;

/**
 * Entité Doctrine « Event ».
 *
 * Table : nom par défaut de Doctrine. Après modification, mettre à jour la base (php bin/console doctrine:schema:update --force en développement).
 */
#[Entity(repositoryClass: EventRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_event_source', columns: ['source', 'external_id'])]
class Event
{
    #[Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    

    

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    /** Lieu physique (salle, mairie, medias) ou "En ligne". */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    /** debat, rencontre_candidat, atelier, permanence */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $kind = null;

    /** Lien d'inscription ou de diffusion (facultatif). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $link = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $proposedBy = null;

    public function getProposedBy(): ?User { return $this->proposedBy; }
    public function setProposedBy(?User $u): self { $this->proposedBy = $u; return $this; }

    /** Evenements importes depuis une source officielle : identifiant de la source, de l'evenement, date de verification. */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $source = null;
    #[ORM\Column(length: 190, nullable: true)]
    private ?string $externalId = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    public function getSource(): ?string { return $this->source; }
    public function getExternalId(): ?string { return $this->externalId; }
    public function getVerifiedAt(): ?\DateTimeImmutable { return $this->verifiedAt; }
    public function markImported(string $source, string $externalId): self { $this->source = $source; $this->externalId = $externalId; $this->verifiedAt = new \DateTimeImmutable(); return $this; }

    /** Types reserves aux imports officiels (non proposables par les membres). */
    public const OFFICIAL_KINDS = [
        'ferie' => 'Jour férié',
        'national' => 'Journée nationale ou internationale',
        'institution' => 'Agenda institutionnel',
    ];
    public static function allKinds(): array { return self::KINDS + self::OFFICIAL_KINDS; }

    public const KINDS = [
        'debat' => 'Débat public',
        'rencontre_candidat' => 'Rencontre avec un candidat ou un élu',
        'atelier' => 'Atelier de transmission',
        'permanence' => 'Permanence d\'écoute',
    ];

    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): self { $this->location = $location; return $this; }
    public function getKind(): ?string { return $this->kind; }
    public function setKind(?string $kind): self { $this->kind = $kind; return $this; }
    public function getKindLabel(): string { return self::allKinds()[$this->kind ?? ''] ?? 'Rencontre'; }
    public function getLink(): ?string { return $this->link; }
    public function setLink(?string $link): self { $this->link = $link; return $this; }

    /**
     * Get the value of id
     */ 
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @return  self
     */ 
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of name
     */ 
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value of name
     *
     * @return  self
     */ 
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the value of date
     */ 
    public function getDate()
    {
        return $this->date;
    }

    /**
     * Set the value of date
     *
     * @return  self
     */ 
    public function setDate($date)
    {
        $this->date = $date;

        return $this;
    }

    /**
     * Get the value of description
     */ 
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set the value of description
     *
     * @return  self
     */ 
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the value of status
     */ 
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set the value of status
     *
     * @return  self
     */ 
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }
}