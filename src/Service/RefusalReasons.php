<?php

namespace App\Service;

use App\Entity\RefusalReason;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Motifs de refus proposes en suggestion dans la file de moderation.
 * Tant que la liste n'a pas ete creee depuis l'administration, on propose les motifs par defaut (ModerationLocks::REASONS).
 */
class RefusalReasons
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return list<string> motifs actifs, dans l'ordre choisi */
    public function active(): array
    {
        try {
            $rows = $this->em->getRepository(RefusalReason::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
        } catch (\Throwable) {
            return ModerationLocks::REASONS; // table pas encore creee : on ne casse pas la moderation
        }
        if (!$rows) {
            return ModerationLocks::REASONS;
        }

        return array_values(array_map(static fn (RefusalReason $r): string => $r->getLabel(), array_filter($rows, static fn (RefusalReason $r): bool => $r->isActive())));
    }

    /** Cree la liste modifiable a partir des motifs par defaut (une seule fois). */
    public function seedDefaults(): int
    {
        if ($this->em->getRepository(RefusalReason::class)->count([]) > 0) {
            return 0;
        }
        foreach (ModerationLocks::REASONS as $i => $label) {
            $this->em->persist(new RefusalReason($label, ($i + 1) * 10));
        }
        $this->em->flush();

        return count(ModerationLocks::REASONS);
    }
}
