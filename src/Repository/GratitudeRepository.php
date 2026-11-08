<?php

namespace App\Repository;

use App\Entity\DiscussionReply;
use App\Entity\Gratitude;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Gratitude> */
class GratitudeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gratitude::class);
    }

    /** Nombre de remerciements d'un donneur vers l'ensemble des reponses d'un meme auteur (anti-collusion). */
    public function countGiverToAuthor(User $giver, User $author): int
    {
        return (int) $this->createQueryBuilder('g')->select('COUNT(g.id)')->join('g.reply', 'r')
            ->andWhere('g.user = :u')->andWhere('r.author = :a')
            ->setParameter('u', $giver)->setParameter('a', $author)->getQuery()->getSingleScalarResult();
    }

    /** @param DiscussionReply[] $replies  @return array<int,int> id de reponse => nombre de remerciements */
    public function countsFor(array $replies): array
    {
        if (!$replies) {
            return [];
        }
        $rows = $this->createQueryBuilder('g')->select('IDENTITY(g.reply) AS id, COUNT(g.id) AS n')
            ->andWhere('g.reply IN (:r)')->setParameter('r', $replies)->groupBy('g.reply')->getQuery()->getArrayResult();

        return array_column($rows, 'n', 'id');
    }

    /** @param DiscussionReply[] $replies  @return int[] ids de reponses deja remerciees par l'utilisateur */
    public function thankedIds(?User $user, array $replies): array
    {
        if (!$user || !$replies) {
            return [];
        }
        $rows = $this->createQueryBuilder('g')->select('IDENTITY(g.reply) AS id')
            ->andWhere('g.user = :u')->andWhere('g.reply IN (:r)')->setParameter('u', $user)->setParameter('r', $replies)
            ->getQuery()->getArrayResult();

        return array_map('intval', array_column($rows, 'id'));
    }

    /**
     * Remerciements recus par auteur : seuls comptent les donneurs actifs, distincts de l'auteur,
     * dont le compte a plus de 7 jours, sur des reponses encore publiees.
     *
     * @return array<int,int> id d'auteur => remerciements
     */
    public function receivedByAuthor(?\DateTimeImmutable $since): array
    {
        $qb = $this->createQueryBuilder('g')->select('IDENTITY(r.author) AS uid, COUNT(g.id) AS n')
            ->join('g.reply', 'r')->join('g.user', 'giver')->join('r.author', 'a')
            ->andWhere('r.status = :s')->setParameter('s', \App\Entity\Proposition::STATUS_VALIDATED)
            ->andWhere('a.rankingOptIn = true')->andWhere('a.isActive = true')
            ->andWhere('giver.isActive = true')->andWhere('giver.id <> a.id')
            ->andWhere('giver.createdAt <= :cut')->setParameter('cut', new \DateTimeImmutable('-7 days'))
            ->groupBy('a.id');
        if ($since) {
            $qb->andWhere('g.createdAt >= :since')->setParameter('since', $since);
        }

        return array_map('intval', array_column($qb->getQuery()->getArrayResult(), 'n', 'uid'));
    }
}
