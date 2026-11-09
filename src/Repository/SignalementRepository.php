<?php

namespace App\Repository;

use App\Entity\Signalement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Signalement> */
class SignalementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Signalement::class);
    }

    /** @return Signalement[] */
    public function findOpen(int $limit = 100): array
    {
        return $this->createQueryBuilder('s')->addSelect('r')->leftJoin('s.reporter', 'r')
            ->andWhere('s.status = :st')->setParameter('st', Signalement::STATUS_OPEN)
            ->orderBy('s.createdAt', 'ASC')->setMaxResults($limit)->getQuery()->getResult();
    }

    public function countOpenForTarget(string $type, int $id): int
    {
        return (int) $this->createQueryBuilder('s')->select('COUNT(DISTINCT s.reporter)')
            ->andWhere('s.targetType = :t')->setParameter('t', $type)
            ->andWhere('s.targetId = :i')->setParameter('i', $id)
            ->andWhere('s.status = :st')->setParameter('st', Signalement::STATUS_OPEN)
            ->getQuery()->getSingleScalarResult();
    }

    public function hasAlreadyReported(User $user, string $type, int $id): bool
    {
        return null !== $this->findOneBy(['reporter' => $user, 'targetType' => $type, 'targetId' => $id]);
    }
}
