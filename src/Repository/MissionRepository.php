<?php
// src/Repository/MissionRepository.php

namespace App\Repository;

use App\Entity\Mission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mission>
 */
class MissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mission::class);
    }

    public function findActiveMissions(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.isActive = :active')
            ->andWhere('m.type = :type')
            ->setParameter('active', true)
            ->setParameter('type', $type)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Piliers, actions et temoignages actifs, dans l'ordre d'affichage choisi dans l'administration. */
    public function findForHome(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.isActive = :a')->andWhere('m.type IN (:t)')
            ->setParameter('a', true)->setParameter('t', ['valeur', 'pilier', 'action', 'temoignage'])
            ->orderBy('m.ordreAffichage', 'ASC')->addOrderBy('m.id', 'ASC')
            ->setMaxResults(12)->getQuery()->getResult();
    }

    public function findMissions(): array
    {
        return $this->findByType('mission');
    }

    public function getStats(): array
    {
        $totalMissions = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_missions' => $totalMissions,
            'benevoles_impliques' => 500 + ($totalMissions * 10),
            'communautes_touchees' => 25 + ($totalMissions * 2),
            'projets_realises' => 150 + ($totalMissions * 5),
        ];
    }
}