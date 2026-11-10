<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Motif de refus propose aux moderateurs (liste modifiable depuis l'administration). */
#[ORM\Entity]
class RefusalReason
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $label;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    public function __construct(string $label, int $position = 0)
    {
        $this->label = mb_substr(trim($label), 0, 200);
        $this->position = $position;
    }

    public function getId(): ?int { return $this->id; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $l): static { $this->label = mb_substr(trim($l), 0, 200); return $this; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $p): static { $this->position = $p; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $a): static { $this->active = $a; return $this; }
}
