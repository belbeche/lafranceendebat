<?php

namespace App\Repository;

use App\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    public function findApprovedCommentsPaginated(int $page, int $limit): array
    {
        if (!is_int($page)) {
            throw new \InvalidArgumentException('The $page parameter must be an integer.');
        }

        $queryBuilder = $this->createQueryBuilder('c')
            ->where('c.isApproved = :approved')
            ->setParameter('approved', true)
            ->orderBy('c.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $queryBuilder->getQuery()->getResult();
    }
}