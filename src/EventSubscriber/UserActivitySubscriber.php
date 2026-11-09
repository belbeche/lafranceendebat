<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\UserEvent;
use App\Service\Security\UserJournal;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Journal des pages visitees par un membre connecte (chemin sans parametres, sans jeton), une fois par page et par 10 minutes.
 * Les visiteurs non connectes ne sont pas suivis ici (le compteur anonymise existant s'en charge).
 */
class UserActivitySubscriber implements EventSubscriberInterface
{
    private const SKIP_PREFIX = ['admin_', '_', 'app_login', 'app_logout', 'app_reset', 'app_forgot', 'app_check_email', 'app_health', 'app_notifications'];

    public function __construct(private readonly TokenStorageInterface $tokens, private readonly UserJournal $journal, private readonly CacheItemPoolInterface $cache)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => ['onController', -10]];
    }

    public function onController(ControllerEvent $e): void
    {
        $r = $e->getRequest();
        if (!$e->isMainRequest() || !$r->isMethod('GET') || $r->isXmlHttpRequest()) {
            return;
        }
        $user = $this->tokens->getToken()?->getUser();
        if (!$user instanceof User || !$user->getId()) {
            return;
        }
        $route = (string) $r->attributes->get('_route', '');
        foreach (self::SKIP_PREFIX as $p) {
            if (str_starts_with($route, $p)) {
                return;
            }
        }
        $path = implode('/', array_map(static fn (string $s): string => preg_match('/^[A-Za-z0-9_-]{24,}$/', $s) ? '…' : $s, explode('/', $r->getPathInfo())));
        $item = $this->cache->getItem('ua_' . md5($user->getId() . '|' . $path));
        if ($item->isHit()) {
            return;
        }
        $this->cache->save($item->set(1)->expiresAfter(600));
        $this->journal->record(UserEvent::PAGE, $user, null, $route, false, $path);
    }
}
