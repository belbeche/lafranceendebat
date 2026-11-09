<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\ModerationLocks;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/** Un contenu pris en charge par un moderateur est verrouille 15 minutes pour les autres. */
class ModerationLockSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ModerationLocks $locks, private readonly TokenStorageInterface $tokens, private readonly UrlGeneratorInterface $urls)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onController', KernelEvents::RESPONSE => 'onResponse'];
    }

    /** @return array{0:string,1:int}|null */
    private function target(\Symfony\Component\HttpFoundation\Request $r): ?array
    {
        $route = $r->attributes->get('_route');
        if (!$r->isMethod('POST') || !array_key_exists($route, ModerationLocks::ROUTES)) {
            return null;
        }
        $kind = ModerationLocks::ROUTES[$route] ?? (string) $r->attributes->get('type');
        $id = $r->attributes->get('id');
        $id = is_object($id) && method_exists($id, 'getId') ? $id->getId() : (int) $id;

        return $kind && $id ? [$kind, (int) $id] : null;
    }

    public function onController(ControllerEvent $e): void
    {
        $user = $this->tokens->getToken()?->getUser();
        if (!$e->isMainRequest() || !$user instanceof User || !($t = $this->target($e->getRequest()))) {
            return;
        }
        try {
            $lock = $this->locks->heldByOther($t[0], $t[1], $user);
        } catch (\Throwable) {
            return; // table pas encore creee : la moderation continue de fonctionner sans verrou
        }
        if ($lock) {
            $e->getRequest()->getSession()->getFlashBag()->add('warning', sprintf('%s a pris ce contenu en charge (jusqu\'à %s). Réessayez ensuite ou demandez-lui de le libérer.', $lock->getUser()->getPublicName(), $lock->getExpiresAt()->format('H:i')));
            $url = $this->urls->generate('admin_moderation');
            $e->setController(static fn () => new RedirectResponse($url));
        }
    }

    public function onResponse(ResponseEvent $e): void
    {
        $user = $this->tokens->getToken()?->getUser();
        $req = $e->getRequest();
        if (!$e->isMainRequest() || !$user instanceof User || !($t = $this->target($req)) || !$e->getResponse()->isRedirection() || !$req->hasSession()) {
            return;
        }
        if ([] !== $req->getSession()->getFlashBag()->peek('success')) {
            try {
                $this->locks->recordDecision($t[0], $t[1], $user);
            } catch (\Throwable) {
            }
        }
    }
}
