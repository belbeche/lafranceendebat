<?php

namespace App\Service\Approval\Provider;

use App\Entity\PositionStatement;
use App\Service\Approval\ApprovalItem;
use App\Service\Approval\ApprovalProvider;
use Doctrine\ORM\EntityManagerInterface;

/** Affirmations de la page Positions pas encore publiées. */
final class StatementApprovalProvider implements ApprovalProvider
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function kind(): string { return 'affirmation'; }
    public function label(): string { return 'Affirmations à publier'; }

    public function pending(int $limit = 50): array
    {
        return array_map(static fn (PositionStatement $s): ApprovalItem => new ApprovalItem(
            'affirmation', (int) $s->getId(), $s->getText(), 'Thème : ' . $s->getTheme(), null, false, null, false,
        ), $this->em->getRepository(PositionStatement::class)->findBy(['published' => false], ['id' => 'ASC'], $limit));
    }

    public function count(): int { return $this->em->getRepository(PositionStatement::class)->count(['published' => false]); }

    public function approve(int $id): bool
    {
        $s = $this->em->find(PositionStatement::class, $id);
        if (!$s || $s->isPublished()) {
            return false;
        }
        $s->setPublished(true);
        $this->em->flush();

        return true;
    }

    public function reject(int $id): bool { return false; }
}
