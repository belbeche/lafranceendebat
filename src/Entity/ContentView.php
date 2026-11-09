<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une lecture d'une publication (debat ou proposition) par un lecteur et par jour.
 * La cle lecteur est une empreinte (adresse IP + navigateur + secret + date) qui change chaque jour :
 * elle ne permet ni de reconnaitre quelqu'un d'un jour a l'autre, ni de remonter a une personne.
 * Le temps de lecture et le defilement ne sont enregistres qu'avec le consentement "mesure de lecture".
 */
#[ORM\Entity]
#[ORM\Table(name: 'content_view')]
#[ORM\UniqueConstraint(name: 'uniq_view_day', columns: ['kind', 'target_id', 'day', 'visitor_key'])]
#[ORM\Index(name: 'idx_view_target', columns: ['kind', 'target_id', 'day'])]
class ContentView
{
    public const DEBAT = 'debat';
    public const PROPOSITION = 'proposition';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 12)]
    private string $kind;

    #[ORM\Column]
    private int $targetId;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $day;

    #[ORM\Column(length: 40)]
    private string $visitorKey;

    /** Secondes de lecture active (0 = non mesure). */
    #[ORM\Column(options: ['default' => 0])]
    private int $readSeconds = 0;

    /** Part de la page parcourue, de 0 a 100 (0 = non mesure). */
    #[ORM\Column(options: ['default' => 0])]
    private int $scrollPct = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $kind, int $targetId, string $visitorKey)
    {
        $this->kind = $kind;
        $this->targetId = $targetId;
        $this->visitorKey = $visitorKey;
        $this->createdAt = new \DateTimeImmutable();
        $this->day = $this->createdAt->setTime(0, 0);
    }

    public function getId(): ?int { return $this->id; }
    public function getReadSeconds(): int { return $this->readSeconds; }
    public function getScrollPct(): int { return $this->scrollPct; }
    public function measure(int $seconds, int $scroll): self
    {
        $this->readSeconds = max($this->readSeconds, max(0, min(1800, $seconds)));
        $this->scrollPct = max($this->scrollPct, max(0, min(100, $scroll)));

        return $this;
    }
}
