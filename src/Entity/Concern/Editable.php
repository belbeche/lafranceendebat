<?php

namespace App\Entity\Concern;

use Doctrine\ORM\Mapping as ORM;

/** Suivi des modifications apres publication : date de derniere modification et nombre de modifications (delai de grace de 5 minutes). */
trait Editable
{
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $editedAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $editCount = 0;

    public function getEditedAt(): ?\DateTimeImmutable { return $this->editedAt; }
    public function getEditCount(): int { return $this->editCount; }

    public function markEdited(\DateTimeImmutable $createdAt): static
    {
        if ($createdAt < new \DateTimeImmutable('-5 minutes')) {
            $this->editedAt = new \DateTimeImmutable();
            ++$this->editCount;
        }

        return $this;
    }
}
