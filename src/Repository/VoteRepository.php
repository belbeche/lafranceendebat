<?php

namespace App\Repository;

use App\Entity\Proposition;
use App\Entity\User;
use App\Entity\Vote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Vote> */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    public function findOneByUserAndProposition(User $user, Proposition $proposition): ?Vote
    {
        return $this->findOneBy(['user' => $user, 'proposition' => $proposition]);
    }

    /**
     * @param Proposition[] $propositions
     * @return int[] identifiants des propositions deja soutenues par l'utilisateur
     */
    public function findSupportedIds(?User $user, array $propositions): array
    {
        if (!$user || !$propositions) {
            return [];
        }
        $rows = $this->createQueryBuilder('v')->select('IDENTITY(v.proposition) AS pid')
            ->andWhere('v.user = :u')->setParameter('u', $user)
            ->andWhere('v.proposition IN (:ps)')->setParameter('ps', $propositions)
            ->getQuery()->getScalarResult();

        return array_map('intval', array_column($rows, 'pid'));
    }
}
