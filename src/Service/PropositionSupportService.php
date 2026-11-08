<?php

namespace App\Service;

use App\Entity\Proposition;
use App\Entity\User;
use App\Entity\Vote;
use App\Repository\VoteRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enregistre ou retire un soutien consultatif de maniere atomique.
 * Un soutien est enregistre dans la meme transaction que l'increment du compteur : le compteur
 * ne peut donc pas diverger du nombre de lignes Vote.
 */
class PropositionSupportService
{
    public const RESULT_ADDED = 'added';
    public const RESULT_ALREADY = 'already';
    public const RESULT_REMOVED = 'removed';
    public const RESULT_NOT_FOUND = 'not_found';
    public const RESULT_UNAVAILABLE = 'unavailable';

    public function __construct(
        private EntityManagerInterface $em,
        private VoteRepository $votes,
    ) {
    }

    public function support(Proposition $proposition, User $user): string
    {
        if (!$proposition->isValidated()) {
            return self::RESULT_UNAVAILABLE;
        }
        if ($this->votes->findOneByUserAndProposition($user, $proposition)) {
            return self::RESULT_ALREADY;
        }

        try {
            $this->em->wrapInTransaction(function () use ($proposition, $user): void {
                $vote = (new Vote())->setProposition($proposition)->setUser($user);
                $this->em->persist($vote);
                $this->em->flush();
                // Increment SQL atomique : evite de perdre un soutien si deux personnes soutiennent en meme temps.
                $this->em->createQuery('UPDATE ' . Proposition::class . ' p SET p.supportCount = p.supportCount + 1 WHERE p.id = :id')
                    ->setParameter('id', $proposition->getId())->execute();
            });
        } catch (UniqueConstraintViolationException) {
            // Double clic ou deux onglets : la contrainte d'unicite protege la base
            // (l'EntityManager est ferme apres cette exception, on ne l'utilise plus).
            return self::RESULT_ALREADY;
        }

        $this->em->refresh($proposition);

        return self::RESULT_ADDED;
    }

    public function withdraw(Proposition $proposition, User $user): string
    {
        $vote = $this->votes->findOneByUserAndProposition($user, $proposition);
        if (!$vote) {
            return self::RESULT_NOT_FOUND;
        }

        $this->em->wrapInTransaction(function () use ($vote, $proposition): void {
            $this->em->remove($vote);
            $this->em->flush();
            $this->em->createQuery('UPDATE ' . Proposition::class . ' p SET p.supportCount = CASE WHEN p.supportCount > 0 THEN p.supportCount - 1 ELSE 0 END WHERE p.id = :id')
                ->setParameter('id', $proposition->getId())->execute();
        });
        $this->em->refresh($proposition);

        return self::RESULT_REMOVED;
    }
}
