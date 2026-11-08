<?php

namespace App\Repository;

use App\Entity\ServiceOffer;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ServiceOffer> */
class ServiceOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceOffer::class);
    }

    /** @return list<ServiceOffer> */
    public function published(?string $category = null): array
    {
        $qb = $this->createQueryBuilder('o')->addSelect('p')->join('o.provider', 'p')
            ->andWhere('o.status = :s')->setParameter('s', ServiceOffer::STATUS_PUBLISHED)->orderBy('o.createdAt', 'DESC')->setMaxResults(60);
        if ($category && isset(ServiceOffer::CATEGORIES[$category])) {
            $qb->andWhere('o.category = :c')->setParameter('c', $category);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<ServiceOffer> */
    public function pending(): array
    {
        return $this->findBy(['status' => ServiceOffer::STATUS_PENDING], ['createdAt' => 'ASC']);
    }

    /** @return list<ServiceOffer> */
    public function byProvider(User $u): array
    {
        return $this->findBy(['provider' => $u], ['createdAt' => 'DESC']);
    }
}
