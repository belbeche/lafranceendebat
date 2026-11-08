<?php

namespace App\Repository;

use App\Entity\Engagement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Engagement> */
class EngagementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Engagement::class);
    }

    /** @return Engagement[] engagements de candidats publies pour un theme */
    public function findByTheme(string $theme): array
    {
        return $this->createQueryBuilder('e')->addSelect('c')->join('e.candidat', 'c')
            ->andWhere('c.isPublished = true')
            ->andWhere('e.theme = :t')->setParameter('t', $theme)
            ->getQuery()->getResult();
    }

    /** @return string[] */
    public function findUsedThemes(): array
    {
        return array_column($this->createQueryBuilder('e')->select('DISTINCT e.theme AS theme')->join('e.candidat', 'c')
            ->andWhere('c.isPublished = true')->orderBy('e.theme', 'ASC')->getQuery()->getArrayResult(), 'theme');
    }
}
