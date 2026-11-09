<?php

namespace App\Service\Approval\Provider;

use App\Entity\PartyProposal;
use App\Service\Approval\ApprovalItem;
use App\Service\Approval\ApprovalProvider;
use Doctrine\ORM\EntityManagerInterface;

/** Hypothèses de chiffrage rédigées par l'IA pour une mesure de parti. Refuser efface l'hypothèse. */
final class PartyAiApprovalProvider implements ApprovalProvider
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function kind(): string { return 'chiffrage_ia'; }
    public function label(): string { return 'Hypothèses de chiffrage (IA)'; }

    private function qb()
    {
        return $this->em->getRepository(PartyProposal::class)->createQueryBuilder('p')->andWhere('p.aiAnalysis IS NOT NULL')->andWhere('p.aiApproved = false');
    }

    public function pending(int $limit = 50): array
    {
        return array_map(static function (PartyProposal $p): ApprovalItem {
            $c = $p->getAiCentralMillions();

            return new ApprovalItem('chiffrage_ia', (int) $p->getId(), $p->getGroupName() . ' : ' . $p->getTitle(),
                null === $c ? 'Hypothèse sans estimation chiffrée.' : sprintf('Hypothèse centrale : %s M€ par an (fiabilité %s/100).', number_format($c, 0, ',', ' '), (string) $p->getAiReliability()),
                $p->getSourceUrl() ?: null, true);
        }, $this->qb()->orderBy('p.id', 'ASC')->setMaxResults($limit)->getQuery()->getResult());
    }

    public function count(): int { return (int) $this->qb()->select('COUNT(p.id)')->getQuery()->getSingleScalarResult(); }

    public function approve(int $id): bool
    {
        $p = $this->em->find(PartyProposal::class, $id);
        if (!$p || null === $p->getAiAnalysis() || $p->isAiApproved()) {
            return false;
        }
        $p->setAiApproved(true);
        $this->em->flush();

        return true;
    }

    public function reject(int $id): bool
    {
        $p = $this->em->find(PartyProposal::class, $id);
        if (!$p || null === $p->getAiAnalysis() || $p->isAiApproved()) {
            return false;
        }
        $p->setAiAnalysis(null);
        $this->em->flush();

        return true;
    }
}
