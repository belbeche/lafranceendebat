<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/** Ferme toute session ouverte avant la derniere "deconnexion des autres appareils". */
class SessionRevocationSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly TokenStorageInterface $tokens, private readonly UrlGeneratorInterface $urls)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 6]]; // apres le pare-feu (8)
    }

    public function onRequest(RequestEvent $e): void
    {
        $user = $this->tokens->getToken()?->getUser();
        if (!$e->isMainRequest() || !$user instanceof User || !($at = $user->getSessionsRevokedAt()) || !$e->getRequest()->hasSession()) {
            return;
        }
        $session = $e->getRequest()->getSession();
        $loginAt = (int) $session->get(LoginHistorySubscriber::SESSION_KEY, 0);
        if ($loginAt >= $at->getTimestamp()) {
            return;
        }
        $session->invalidate();
        $this->tokens->setToken(null);
        $e->setResponse(new RedirectResponse($this->urls->generate('app_login')));
    }
}
