<?php

namespace App\Service;

use App\Entity\Proposition;
use App\Entity\User;
use App\Repository\GratitudeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Classement des "artisans de paix" : il mesure la qualite du dialogue, pas la popularite.
 *  - 1 point par remerciement recu d'une personne distincte (compte de plus de 7 jours, hors soi-meme,
 *    au plus 3 remerciements d'une meme personne vers un meme auteur) ; plafond de 50 points ;
 *  - 2 points par reponse publiee qui commence par une reformulation de la position de l'autre ; plafond de 20 points.
 * Seules les personnes ayant choisi d'y figurer apparaissent. Un contenu masque ne rapporte plus rien.
 */
class RankingService
{
    public const THANKS_CAP = 50;
    public const REFORMULATION_CAP = 20;
    public const TIERS = [50 => 'Artisan de paix', 25 => 'Médiateur', 10 => 'Bâtisseur de ponts', 1 => 'Semeur de dialogue'];

    public function __construct(private GratitudeRepository $gratitudes, private EntityManagerInterface $em)
    {
    }

    /** @return list<array{user: User, score: int, thanks: int, reformulations: int, tier: string, rank: int}> */
    public function top(string $period, int $limit = 20): array
    {
        $since = $period === 'mois' ? new \DateTimeImmutable('first day of this month 00:00:00') : null;
        $thanks = $this->gratitudes->receivedByAuthor($since);
        $refs = $this->reformulations($since);

        $scores = [];
        foreach (array_unique(array_merge(array_keys($thanks), array_keys($refs))) as $uid) {
            $score = min($thanks[$uid] ?? 0, self::THANKS_CAP) + min(2 * ($refs[$uid] ?? 0), self::REFORMULATION_CAP);
            if ($score > 0) {
                $scores[$uid] = $score;
            }
        }
        arsort($scores);
        $scores = array_slice($scores, 0, $limit, true);
        if (!$scores) {
            return [];
        }

        $users = [];
        foreach ($this->em->getRepository(User::class)->findBy(['id' => array_keys($scores), 'rankingOptIn' => true, 'isActive' => true]) as $u) {
            $users[$u->getId()] = $u;
        }

        $out = [];
        $rank = 0;
        foreach ($scores as $uid => $score) {
            if (!isset($users[$uid])) {
                continue;
            }
            $out[] = ['user' => $users[$uid], 'score' => $score, 'thanks' => $thanks[$uid] ?? 0, 'reformulations' => $refs[$uid] ?? 0, 'tier' => $this->tier($score), 'rank' => ++$rank];
        }

        return $out;
    }

    /** Badge public (niveau, points) pour les personnes qui ont choisi d'apparaître au classement. @return array<int, array{tier: string, score: int, rank: int}> indexé par id utilisateur */
    public function badges(): array
    {
        static $cache = null;
        if (null === $cache) {
            $cache = [];
            foreach ($this->top('tout', 500) as $r) {
                $cache[$r['user']->getId()] = ['tier' => $r['tier'], 'score' => $r['score'], 'rank' => $r['rank']];
            }
        }

        return $cache;
    }

    /** Points de la personne (même si elle n'est pas au classement), pour lui montrer sa progression. @return array{score: int, tier: ?string, next: ?int, nextTier: ?string} */
    public function progress(User $u): array
    {
        $uid = $u->getId();
        $thanks = $this->gratitudes->receivedByAuthor(null)[$uid] ?? 0;
        $refs = $this->reformulations(null)[$uid] ?? 0;
        $score = min($thanks, self::THANKS_CAP) + min(2 * $refs, self::REFORMULATION_CAP);
        $next = null;
        $nextTier = null;
        foreach (array_reverse(self::TIERS, true) as $min => $label) {
            if ($min > $score) {
                $next = $min - $score;
                $nextTier = $label;
                break;
            }
        }

        return ['score' => $score, 'tier' => $score > 0 ? $this->tier($score) : null, 'next' => $next, 'nextTier' => $nextTier];
    }

    public function tier(int $score): string
    {
        foreach (self::TIERS as $min => $label) {
            if ($score >= $min) {
                return $label;
            }
        }

        return '';
    }

    /** @return array<int,int> id d'auteur => nombre de reponses publiees avec reformulation */
    private function reformulations(?\DateTimeImmutable $since): array
    {
        $out = [];
        foreach (['App\Entity\DiscussionReply', 'App\Entity\Argument'] as $class) {
            $qb = $this->em->createQueryBuilder()->select('IDENTITY(x.author) AS uid, COUNT(x.id) AS n')->from($class, 'x')
                ->join('x.author', 'a')
                ->andWhere('x.status = :s')->setParameter('s', Proposition::STATUS_VALIDATED)
                ->andWhere('x.reformulation IS NOT NULL')->andWhere("x.reformulation <> ''")
                ->andWhere('a.rankingOptIn = true')->andWhere('a.isActive = true')->groupBy('a.id');
            if ($since) {
                $qb->andWhere('x.createdAt >= :since')->setParameter('since', $since);
            }
            foreach ($qb->getQuery()->getArrayResult() as $row) {
                $out[(int) $row['uid']] = ($out[(int) $row['uid']] ?? 0) + (int) $row['n'];
            }
        }

        return $out;
    }
}
