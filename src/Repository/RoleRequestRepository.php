<?php

namespace App\Repository;

use App\Entity\RoleRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RoleRequest> */
class RoleRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoleRequest::class);
    }

    public function hasPending(User $u, string $role): bool
    {
        return $this->count(['user' => $u, 'role' => $role, 'status' => RoleRequest::STATUS_PENDING]) > 0;
    }

    public function lastRejection(User $u, string $role): ?RoleRequest
    {
        return $this->findOneBy(['user' => $u, 'role' => $role, 'status' => RoleRequest::STATUS_REJECTED], ['decidedAt' => 'DESC']);
    }

    /** @return list<RoleRequest> */
    public function pending(): array
    {
        return $this->findBy(['status' => RoleRequest::STATUS_PENDING], ['createdAt' => 'ASC']);
    }

    /** @return list<RoleRequest> */
    public function recent(int $limit = 50): array
    {
        return $this->createQueryBuilder('r')->andWhere('r.status != :p')->setParameter('p', RoleRequest::STATUS_PENDING)
            ->orderBy('r.decidedAt', 'DESC')->setMaxResults($limit)->getQuery()->getResult();
    }

    /** @return list<RoleRequest> */
    public function findByUser(User $u): array
    {
        return $this->findBy(['user' => $u], ['createdAt' => 'DESC']);
    }
}
