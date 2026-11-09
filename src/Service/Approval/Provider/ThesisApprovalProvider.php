<?php

namespace App\Service\Approval\Provider;

use App\Entity\Thesis;
use App\Service\Approval\ApprovalItem;
use App\Service\Approval\ApprovalProvider;
use Doctrine\ORM\EntityManagerInterface;

/** Analyses sourcées non publiées. Pas de refus : une analyse non approuvée reste simplement invisible. */
final class ThesisApprovalProvider implements ApprovalProvider
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function kind(): string { return 'analyse'; }
    public function label(): string { return 'Analyses sourcées'; }

    public function pending(int $limit = 50): array
    {
        return array_map(static fn (Thesis $t): ApprovalItem => new ApprovalItem(
            'analyse', (int) $t->getId(), $t->getTitle(),
            $t->isAiGenerated() ? 'Rédigée par l\'IA, à relire avant publication.' : 'Rédigée par l\'éditeur.',
            null, $t->isAiGenerated(), null, false,
        ), $this->em->getRepository(Thesis::class)->findBy(['approved' => false], ['id' => 'ASC'], $limit));
    }

    public function count(): int { return $this->em->getRepository(Thesis::class)->count(['approved' => false]); }

    public function approve(int $id): bool
    {
        $t = $this->em->find(Thesis::class, $id);
        if (!$t || $t->isApproved()) {
            return false;
        }
        $t->setApproved(true);
        $this->em->flush();

        return true;
    }

    public function reject(int $id): bool { return false; }
}
