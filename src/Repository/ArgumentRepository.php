<?php

namespace App\Repository;

use App\Entity\Argument;
use App\Entity\Debat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Argument> */
class ArgumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Argument::class);
    }

    /**
     * Arguments racines visibles d'un debat. Les reponses sont filtrees dans le gabarit (isVisible).
     *
     * @return Argument[]
     */
    public function findVisibleRoots(Debat $debat): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('u', 'r')
            ->leftJoin('a.author', 'u')
            ->leftJoin('a.replies', 'r')
            ->andWhere('a.debat = :d')->setParameter('d', $debat)
            ->andWhere('a.parent IS NULL')
            ->andWhere('a.status = :s')->setParameter('s', Argument::STATUS_VALIDATED)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return Argument[] */
    public function findPending(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')->addSelect('d', 'u')->join('a.debat', 'd')->leftJoin('a.author', 'u')
            ->andWhere('a.status = :s')->setParameter('s', Argument::STATUS_PENDING)
            ->orderBy('a.createdAt', 'ASC')->setMaxResults($limit)->getQuery()->getResult();
    }

    /** @return array<string, int> */
    public function countVisibleByStance(Debat $debat): array
    {
        $out = array_fill_keys(array_keys(Argument::STANCES), 0);
        $rows = $this->createQueryBuilder('a')->select('a.stance AS stance, COUNT(a.id) AS total')
            ->andWhere('a.debat = :d')->setParameter('d', $debat)
            ->andWhere('a.parent IS NULL')
            ->andWhere('a.status = :s')->setParameter('s', Argument::STATUS_VALIDATED)
            ->groupBy('a.stance')->getQuery()->getArrayResult();
        foreach ($rows as $r) {
            $out[$r['stance']] = (int) $r['total'];
        }

        return $out;
    }

    /** @return array<string, int> */
    public function statsByStance(): array
    {
        $out = array_fill_keys(array_keys(Argument::STANCES), 0);
        $rows = $this->createQueryBuilder('a')->select('a.stance AS stance, COUNT(a.id) AS total')
            ->andWhere('a.status = :s')->setParameter('s', Argument::STATUS_VALIDATED)
            ->groupBy('a.stance')->getQuery()->getArrayResult();
        foreach ($rows as $r) {
            $out[$r['stance']] = (int) $r['total'];
        }

        return $out;
    }
}
