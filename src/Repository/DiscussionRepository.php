<?php

namespace App\Repository;

use App\Entity\Discussion;
use App\Entity\Proposition;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Discussion> */
class DiscussionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Discussion::class);
    }

    /** @return Discussion[] */
    public function findPublic(?string $category, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('d')->addSelect('u')->leftJoin('d.author', 'u')
            ->andWhere('d.status = :s')->setParameter('s', Proposition::STATUS_VALIDATED)
            ->orderBy('d.pinned', 'DESC')->addOrderBy('d.lastActivityAt', 'DESC')->setMaxResults($limit);
        if ($category) {
            $qb->andWhere('d.category = :c')->setParameter('c', $category);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Discussion[] */
    public function findPending(int $limit = 50): array
    {
        return $this->createQueryBuilder('d')->addSelect('u')->leftJoin('d.author', 'u')
            ->andWhere('d.status = :s')->setParameter('s', Proposition::STATUS_PENDING)
            ->orderBy('d.createdAt', 'ASC')->setMaxResults($limit)->getQuery()->getResult();
    }

    public function countSince(User $author, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('d')->select('COUNT(d.id)')
            ->andWhere('d.author = :a')->andWhere('d.createdAt >= :t')
            ->setParameter('a', $author)->setParameter('t', $since)->getQuery()->getSingleScalarResult();
    }

    /** @return array<int, int> id de fil => nombre de reponses visibles */
    public function replyCounts(array $discussions): array
    {
        if (!$discussions) {
            return [];
        }
        $rows = $this->getEntityManager()->createQuery(
            'SELECT IDENTITY(r.discussion) AS id, COUNT(r.id) AS n FROM App\Entity\DiscussionReply r WHERE r.discussion IN (:d) AND r.status = :s GROUP BY r.discussion'
        )->setParameter('d', $discussions)->setParameter('s', Proposition::STATUS_VALIDATED)->getArrayResult();

        return array_column($rows, 'n', 'id');
    }
}
