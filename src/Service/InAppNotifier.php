<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/** Crée la notification visible dans la cloche. Ne doit jamais bloquer l'action de l'utilisateur. */
class InAppNotifier
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function notify(User $to, string $text, string $url): void
    {
        try {
            $path = parse_url($url, PHP_URL_PATH) ?: '/';
            $q = parse_url($url, PHP_URL_QUERY);
            $this->em->persist(new Notification($to, $text, $path . ($q ? '?' . $q : '')));
            $this->em->flush();
        } catch (\Throwable) {
        }
    }
}
