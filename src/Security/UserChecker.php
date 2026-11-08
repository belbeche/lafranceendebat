<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Refuse la connexion (et invalide les sessions ouvertes) d'un compte suspendu. */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof User && (!$user->isEnabled() || !$user->isActive())) {
            throw new CustomUserMessageAccountStatusException('Ce compte est suspendu. Contactez la modération.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
