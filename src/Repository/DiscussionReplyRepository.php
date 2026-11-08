<?php

namespace App\Repository;

use App\Entity\Discussion;
use App\Entity\DiscussionReply;
use App\Entity\Proposition;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DiscussionReply> */
class DiscussionReplyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiscussionReply::class);
    }

    /** @return DiscussionReply[] */
    public function findVisible(Discussion $d): array
    {
        return $this->createQueryBuilder('r')->addSelect('u')->leftJoin('r.author', 'u')
            ->andWhere('r.discussion = :d')->andWhere('r.status = :s')
            ->setParameter('d', $d)->setParameter('s', Proposition::STATUS_VALIDATED)
            ->orderBy('r.createdAt', 'ASC')->getQuery()->getResult();
    }

    /** @return DiscussionReply[] */
    public function findPending(int $limit = 50): array
    {
        return $this->createQueryBuilder('r')->addSelect('d', 'u')->join('r.discussion', 'd')->leftJoin('r.author', 'u')
            ->andWhere('r.status = :s')->setParameter('s', Proposition::STATUS_PENDING)
            ->orderBy('r.createdAt', 'ASC')->setMaxResults($limit)->getQuery()->getResult();
    }

    public function countSince(User $author, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('r')->select('COUNT(r.id)')
            ->andWhere('r.author = :a')->andWhere('r.createdAt >= :t')
            ->setParameter('a', $author)->setParameter('t', $since)->getQuery()->getSingleScalarResult();
    }
}
