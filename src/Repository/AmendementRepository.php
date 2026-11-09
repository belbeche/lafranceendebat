<?php

namespace App\Repository;

use App\Entity\Amendement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Amendement> */
class AmendementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Amendement::class);
    }

    /** @return Amendement[] */
    public function findPending(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')->addSelect('p', 'u')->join('a.proposition', 'p')->leftJoin('a.author', 'u')
            ->andWhere('a.status = :s')->setParameter('s', 'En attente')
            ->orderBy('a.createdAt', 'ASC')->setMaxResults($limit)->getQuery()->getResult();
    }
}
