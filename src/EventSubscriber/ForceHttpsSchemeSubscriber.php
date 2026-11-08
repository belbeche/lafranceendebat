<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Derrière Cloudflare / un proxy non déclaré dans TRUSTED_PROXIES, Symfony croit être en http
 * et génère des URL absolues en http (sitemap, canonical, e-mails). En production, le site n'est
 * servi qu'en https : on force le schéma, sauf pour un hôte local.
 */
final class ForceHttpsSchemeSubscriber implements EventSubscriberInterface
{
    public function __construct(#[Autowire('%kernel.environment%')] private string $env)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 300]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if ('prod' !== $this->env || !$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $host = $request->getHost();
        if ($request->isSecure() || 'localhost' === $host || str_ends_with($host, '.localhost') || '127.0.0.1' === $host) {
            return;
        }
        $request->server->set('HTTPS', 'on');
        $request->server->set('SERVER_PORT', 443);
        $request->headers->set('X-Forwarded-Proto', 'https');
        // Le contexte du routeur est reconstruit à partir de la requête.
        $request->initialize($request->query->all(), $request->request->all(), $request->attributes->all(), $request->cookies->all(), $request->files->all(), $request->server->all(), $request->getContent());
    }
}
