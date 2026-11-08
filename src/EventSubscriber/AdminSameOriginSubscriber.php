<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Defense en profondeur : toute requete modifiante (POST, PUT, PATCH, DELETE) vers l'administration
 * doit provenir du meme site (en-tetes Origin / Referer), en plus du jeton CSRF et du cookie SameSite.
 */
class AdminSameOriginSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 64]];
    }

    public function onRequest(RequestEvent $event): void
    {
        $r = $event->getRequest();
        if (!$event->isMainRequest() || $r->isMethodSafe() || !str_starts_with($r->getPathInfo(), '/admin')) {
            return;
        }
        $source = $r->headers->get('Origin') ?: $r->headers->get('Referer');
        $host = $source ? parse_url($source, PHP_URL_HOST) : null;
        $port = $source ? parse_url($source, PHP_URL_PORT) : null;
        $sameHost = is_string($host) && strcasecmp($host, $r->getHost()) === 0;
        $samePort = null === $port || (int) $port === (int) $r->getPort();
        if (!$sameHost || !$samePort) {
            $event->setResponse(new Response('Requête refusée : origine inattendue.', Response::HTTP_FORBIDDEN));
        }
    }
}
