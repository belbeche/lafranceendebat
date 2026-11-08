<?php

namespace App\Repository;

use App\Entity\ServiceOrder;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ServiceOrder> */
class ServiceOrderRepository extends ServiceEntityRepository
{
    public const AUTO_RELEASE_DAYS = 14;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceOrder::class);
    }

    /** @return list<ServiceOrder> */
    public function forProvider(User $u): array
    {
        return $this->createQueryBuilder('r')->join('r.offer', 'o')->andWhere('o.provider = :u')->setParameter('u', $u)
            ->andWhere('r.status != :p')->setParameter('p', ServiceOrder::PENDING)->orderBy('r.createdAt', 'DESC')->getQuery()->getResult();
    }

    /** @return list<ServiceOrder> */
    public function forBuyer(User $u): array
    {
        return $this->createQueryBuilder('r')->andWhere('r.buyer = :u')->setParameter('u', $u)->orderBy('r.createdAt', 'DESC')->getQuery()->getResult();
    }

    /** @return list<ServiceOrder> commandes payees depuis plus de $days jours, jamais confirmees */
    public function dueForRelease(int $days): array
    {
        return $this->createQueryBuilder('r')->andWhere('r.status = :s')->setParameter('s', ServiceOrder::PAID)
            ->andWhere('r.paidAt <= :d')->setParameter('d', new \DateTimeImmutable("-$days days"))->getQuery()->getResult();
    }
}
