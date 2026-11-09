<?php

namespace App\Repository;

use App\Entity\Proposition;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Proposition> */
class PropositionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Proposition::class);
    }

    /** @return Proposition[] propositions validees, les plus soutenues d'abord */
    public function findValidatedRanked(?string $theme = null, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('a')
            ->leftJoin('p.author', 'a')
            ->andWhere('p.status = :status')->setParameter('status', Proposition::STATUS_VALIDATED)
            ->orderBy('p.supportCount', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)->setFirstResult($offset);
        if ($theme) {
            $qb->andWhere('p.theme = :theme')->setParameter('theme', $theme);
        }

        return $qb->getQuery()->getResult();
    }

    public function countValidated(?string $theme = null): int
    {
        $qb = $this->createQueryBuilder('p')->select('COUNT(p.id)')
            ->andWhere('p.status = :status')->setParameter('status', Proposition::STATUS_VALIDATED);
        if ($theme) {
            $qb->andWhere('p.theme = :theme')->setParameter('theme', $theme);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** @return Proposition[] */
    public function findPending(int $limit = 50): array
    {
        return $this->createQueryBuilder('p')->addSelect('a')->leftJoin('p.author', 'a')
            ->andWhere('p.status = :s')->setParameter('s', Proposition::STATUS_PENDING)
            ->orderBy('p.createdAt', 'ASC')->setMaxResults($limit)->getQuery()->getResult();
    }

    /** @return Proposition[] */
    public function findByAuthor(User $user): array
    {
        return $this->findBy(['author' => $user], ['createdAt' => 'DESC']);
    }

    /** @return array<int, array{theme: string, total: int, supports: int}> */
    public function statsByTheme(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.theme AS theme, COUNT(p.id) AS total, COALESCE(SUM(p.supportCount), 0) AS supports')
            ->andWhere('p.status = :s')->setParameter('s', Proposition::STATUS_VALIDATED)
            ->groupBy('p.theme')->orderBy('total', 'DESC')->getQuery()->getArrayResult();

        return array_map(fn ($r) => ['theme' => $r['theme'], 'total' => (int) $r['total'], 'supports' => (int) $r['supports']], $rows);
    }

    /** @return array<string, int> */
    public function statsByStatus(): array
    {
        $out = array_fill_keys(Proposition::STATUSES, 0);
        $rows = $this->createQueryBuilder('p')->select('p.status AS status, COUNT(p.id) AS total')
            ->groupBy('p.status')->getQuery()->getArrayResult();
        foreach ($rows as $r) {
            $out[$r['status']] = (int) $r['total'];
        }

        return $out;
    }

    /** Themes ayant au moins une proposition validee. @return string[] */
    public function findUsedThemes(): array
    {
        return array_column($this->createQueryBuilder('p')->select('DISTINCT p.theme AS theme')
            ->andWhere('p.status = :s')->setParameter('s', Proposition::STATUS_VALIDATED)
            ->orderBy('p.theme', 'ASC')->getQuery()->getArrayResult(), 'theme');
    }

    /** @return list<array{p:Proposition,n:int}> propositions les plus soutenues sur les 7 derniers jours */
    public function findTrending(int $limit = 20): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(v.proposition) AS pid, COUNT(v.id) AS n')
            ->from(\App\Entity\Vote::class, 'v')
            ->join('v.proposition', 'p')
            ->andWhere('v.createdAt >= :since')->setParameter('since', new \DateTimeImmutable('-7 days'))
            ->andWhere('p.status = :s')->setParameter('s', Proposition::STATUS_VALIDATED)
            ->groupBy('v.proposition')->orderBy('n', 'DESC')->setMaxResults($limit)
            ->getQuery()->getArrayResult();
        $out = [];
        foreach ($rows as $r) {
            if ($p = $this->find((int) $r['pid'])) {
                $out[] = ['p' => $p, 'n' => (int) $r['n']];
            }
        }

        return $out;
    }

    /** @return Proposition[] petitions validees, les plus avancees vers leur objectif d'abord */
    public function findPetitions(int $limit = 50): array
    {
        $all = $this->createQueryBuilder('p')
            ->andWhere('p.status = :s')->setParameter('s', Proposition::STATUS_VALIDATED)
            ->andWhere('p.petition = true')->getQuery()->getResult();
        usort($all, static fn (Proposition $a, Proposition $b) => $b->getSupportCount() <=> $a->getSupportCount());

        return array_slice($all, 0, $limit);
    }
}
