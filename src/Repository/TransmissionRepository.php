<?php

namespace App\Repository;

use App\Entity\Transmission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Transmission> */
class TransmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transmission::class);
    }

    /** @return Transmission[] */
    public function findAllPublic(): array
    {
        return $this->createQueryBuilder('t')->addSelect('r', 'p')
            ->leftJoin('t.revendication', 'r')->leftJoin('t.proposition', 'p')
            ->orderBy('t.updatedAt', 'DESC')->getQuery()->getResult();
    }
}
