<?php

namespace App\Repository;

use App\Entity\Consultation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Consultation> */
class ConsultationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consultation::class);
    }

    /** @return list<Consultation> */
    public function findOpen(): array
    {
        return $this->createQueryBuilder('c')->andWhere('c.opensAt <= :n')->andWhere('c.closesAt > :n')
            ->setParameter('n', new \DateTimeImmutable())->orderBy('c.closesAt', 'ASC')->getQuery()->getResult();
    }

    /** @return list<Consultation> */
    public function findClosed(int $limit = 20): array
    {
        return $this->createQueryBuilder('c')->andWhere('c.closesAt <= :n')
            ->setParameter('n', new \DateTimeImmutable())->orderBy('c.closesAt', 'DESC')->setMaxResults($limit)->getQuery()->getResult();
    }
}
