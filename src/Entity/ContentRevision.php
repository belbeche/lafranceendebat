<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Ancienne version d'un contenu modifie apres publication (transparence : les lecteurs voient ce qui a change). */
#[ORM\Entity]
#[ORM\Index(columns: ['type', 'ref_id'])]
class ContentRevision
{
    public const TYPES = ['debat', 'argument', 'proposition', 'discussion', 'reponse', 'revendication', 'actualite', 'dossier'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $type;

    #[ORM\Column(name: 'ref_id')]
    private int $refId;

    /** Valeurs AVANT la modification, par champ. */
    #[ORM\Column(type: Types::JSON)]
    private array $oldValues;

    #[ORM\Column(options: ['default' => false])]
    private bool $byModerator = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $type, int $refId, array $oldValues, bool $byModerator)
    {
        $this->type = $type;
        $this->refId = $refId;
        $this->oldValues = $oldValues;
        $this->byModerator = $byModerator;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getType(): string { return $this->type; }
    public function getRefId(): int { return $this->refId; }
    public function getOldValues(): array { return $this->oldValues; }
    public function isByModerator(): bool { return $this->byModerator; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
