<?php

namespace App\Repository;

use App\Entity\Ballot;
use App\Entity\Consultation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Ballot> */
class BallotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ballot::class);
    }

    public function findOneFor(Consultation $c, User $u): ?Ballot
    {
        return $this->findOneBy(['consultation' => $c, 'user' => $u]);
    }

    /** @return array<int,int> index d'option => nombre de bulletins */
    public function tally(Consultation $c): array
    {
        $rows = $this->createQueryBuilder('b')->select('b.choice AS ch, COUNT(b.id) AS n')
            ->andWhere('b.consultation = :c')->setParameter('c', $c)->groupBy('b.choice')->getQuery()->getArrayResult();
        $out = array_fill(0, count($c->getOptions()), 0);
        foreach ($rows as $r) {
            $out[(int) $r['ch']] = (int) $r['n'];
        }

        return $out;
    }
}
