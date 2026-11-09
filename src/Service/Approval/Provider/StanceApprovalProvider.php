<?php

namespace App\Service\Approval\Provider;

use App\Entity\PositionStance;
use App\Service\Approval\ApprovalItem;
use App\Service\Approval\ApprovalProvider;
use Doctrine\ORM\EntityManagerInterface;

/** Positions de candidats proposées (surtout par la recherche IA), invisibles tant qu'elles ne sont pas validées. */
final class StanceApprovalProvider implements ApprovalProvider
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function kind(): string { return 'position'; }
    public function label(): string { return 'Positions des candidats'; }

    public function pending(int $limit = 50): array
    {
        $rows = $this->em->getRepository(PositionStance::class)->findBy(['status' => PositionStance::PENDING], ['id' => 'ASC'], $limit);

        return array_map(static fn (PositionStance $x): ApprovalItem => new ApprovalItem(
            'position', (int) $x->getId(),
            $x->getCandidat()->getName() . ' : ' . $x->getStanceLabel(),
            $x->getStatement()->getText() . ' / ' . $x->getSummary(),
            $x->getSourceUrl(), $x->isAiProposed(),
        ), $rows);
    }

    public function count(): int
    {
        return $this->em->getRepository(PositionStance::class)->count(['status' => PositionStance::PENDING]);
    }

    public function approve(int $id): bool { return $this->set($id, PositionStance::APPROVED); }
    public function reject(int $id): bool { return $this->set($id, PositionStance::REJECTED); }

    private function set(int $id, string $status): bool
    {
        $x = $this->em->find(PositionStance::class, $id);
        if (!$x || PositionStance::PENDING !== $x->getStatus()) {
            return false;
        }
        $x->setStatus($status);
        $this->em->flush();

        return true;
    }
}
