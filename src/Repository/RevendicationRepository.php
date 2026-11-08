<?php

namespace App\Repository;

use App\Entity\Revendication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Revendication> */
class RevendicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Revendication::class);
    }

    /** @return Revendication[] */
    public function findPublished(?string $group = null, ?string $status = null, ?string $locale = null): array
    {
        $qb = $this->createQueryBuilder('r')->andWhere('r.isPublished = true')
            ->orderBy('r.updatedAt', 'ASC'); // les plus anciennes sans suite d'abord : on ne les oublie pas
        if ($locale) {
            $qb->andWhere('r.locale = :loc')->setParameter('loc', $locale);
        }
        if ($group) {
            $qb->andWhere('r.groupLabel = :g')->setParameter('g', $group);
        }
        if ($status) {
            $qb->andWhere('r.status = :s')->setParameter('s', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /** Revendications dans la langue du lecteur ; si aucune, toutes langues confondues (rien n'est oublié). */
    public function findPublishedForReader(string $locale, ?string $group = null, ?string $status = null): array
    {
        // Toutes les revendications sont listées : la traduction automatique les affiche dans la langue du lecteur.
        return $this->findPublished($group, $status);
    }

    public function findPublishedBySlug(string $slug): ?Revendication
    {
        return $this->findOneBy(['slug' => $slug, 'isPublished' => true]);
    }

    public function countAwaitingAnswer(): int
    {
        return (int) $this->createQueryBuilder('r')->select('COUNT(r.id)')
            ->andWhere('r.isPublished = true')
            ->andWhere('r.status IN (:s)')->setParameter('s', [Revendication::STATUS_RAISED, Revendication::STATUS_TRANSMITTED, Revendication::STATUS_NO_FOLLOW_UP])
            ->getQuery()->getSingleScalarResult();
    }
}
