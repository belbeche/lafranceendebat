<?php

namespace App\Service\Approval\Provider;

use App\Entity\PartyProposal;
use App\Service\Approval\ApprovalItem;
use App\Service\Approval\ApprovalProvider;
use Doctrine\ORM\EntityManagerInterface;

/** Mesures de programme importées masquées (ex. programmes 2027). Les publier les rend visibles ; pas de refus. */
final class PartyProposalApprovalProvider implements ApprovalProvider
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function kind(): string { return 'mesure'; }
    public function label(): string { return 'Mesures de programme masquées'; }

    public function pending(int $limit = 50): array
    {
        return array_map(static fn (PartyProposal $p): ApprovalItem => new ApprovalItem(
            'mesure', (int) $p->getId(), $p->getGroupName() . ' : ' . $p->getTitle(), (string) $p->getSummary(), $p->getSourceUrl() ?: null, false, null, false,
        ), $this->em->getRepository(PartyProposal::class)->findBy(['published' => false], ['id' => 'ASC'], $limit));
    }

    public function count(): int { return $this->em->getRepository(PartyProposal::class)->count(['published' => false]); }

    public function approve(int $id): bool
    {
        $p = $this->em->find(PartyProposal::class, $id);
        if (!$p || $p->isPublished()) {
            return false;
        }
        $p->setPublished(true);
        $this->em->flush();

        return true;
    }

    public function reject(int $id): bool { return false; }
}
