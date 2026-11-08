<?php

namespace App\Repository;

use App\Entity\Debat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Debat> */
class DebatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Debat::class);
    }

    /** @return Debat[] debats publics (ouverts puis clos) */
    public function findPublic(?string $theme = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.status IN (:s)')->setParameter('s', [Debat::STATUS_OPEN, Debat::STATUS_CLOSED])
            ->orderBy('d.status', 'DESC') // "open" avant "closed"
            ->addOrderBy('d.createdAt', 'DESC');
        if ($theme) {
            $qb->andWhere('d.theme = :t')->setParameter('t', $theme);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<string> thèmes libres déjà utilisés par des débats publics (hors thèmes prédéfinis) */
    public function customThemes(): array
    {
        $rows = $this->createQueryBuilder('d')->select('DISTINCT d.theme AS t')
            ->andWhere('d.status IN (:s)')->setParameter('s', [Debat::STATUS_OPEN, Debat::STATUS_CLOSED])
            ->andWhere('d.theme NOT IN (:p)')->setParameter('p', \App\Entity\Proposition::THEMES)
            ->orderBy('d.theme', 'ASC')->getQuery()->getScalarResult();

        return array_values(array_map(static fn (array $r) => (string) $r['t'], $rows));
    }

    /** @return Debat[] */
    public function findOpen(int $limit = 3): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :s')->setParameter('s', Debat::STATUS_OPEN)
            ->orderBy('d.createdAt', 'DESC')->setMaxResults($limit)->getQuery()->getResult();
    }

    public function findPublicBySlug(string $slug): ?Debat
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.slug = :slug')->setParameter('slug', $slug)
            ->andWhere('d.status IN (:s)')->setParameter('s', [Debat::STATUS_OPEN, Debat::STATUS_CLOSED])
            ->getQuery()->getOneOrNullResult();
    }
}
