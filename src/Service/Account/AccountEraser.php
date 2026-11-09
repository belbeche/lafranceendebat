<?php

namespace App\Service\Account;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Suppression d'un compte (droit a l'effacement, ou inactivite) : les commentaires et soutiens sont supprimes,
 * les propositions et arguments publies restent affiches sans lien vers l'auteur. Meme regle partout.
 */
class AccountEraser
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function erase(User $user): void
    {
        foreach ($user->getComments() as $comment) {
            $this->em->remove($comment);
        }
        $this->em->remove($user);
        $this->em->flush();
    }
}
