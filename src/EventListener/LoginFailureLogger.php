<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/** Trace la CAUSE d'un echec de connexion (jamais le mot de passe ni l'e-mail complet) pour pouvoir diagnostiquer. */
#[AsEventListener]
class LoginFailureLogger
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(LoginFailureEvent $e): void
    {
        $this->logger->warning('Echec de connexion', [
            'cause' => $e->getException()::class,
            'ip' => $e->getRequest()->getClientIp(),
            'session_cookie_recu' => $e->getRequest()->cookies->has(session_name()),
            'https' => $e->getRequest()->isSecure(),
        ]);
    }
}
