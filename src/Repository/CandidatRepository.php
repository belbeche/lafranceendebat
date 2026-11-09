<?php

namespace App\Repository;

use App\Entity\Candidat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Candidat> */
class CandidatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Candidat::class);
    }

    /** @return Candidat[] publies, ordre alphabetique (jamais un classement) */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('c')->andWhere('c.isPublished = true')->orderBy('c.name', 'ASC')->getQuery()->getResult();
    }

    public function findPublishedBySlug(string $slug): ?Candidat
    {
        return $this->findOneBy(['slug' => $slug, 'isPublished' => true]);
    }
}
