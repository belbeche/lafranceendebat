<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\LogoutException;

/**
 * Un lien "Se deconnecter" dont le jeton a expire (page restee ouverte, session renouvelee, autre serveur) ne doit pas
 * afficher une erreur 403 : on revient simplement a l'accueil. Le jeton CSRF reste exige, on ne deconnecte personne a son insu.
 */
final class LogoutCsrfSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 64]];
    }

    public function onException(ExceptionEvent $event): void
    {
        for ($e = $event->getThrowable(); null !== $e; $e = $e->getPrevious()) {
            if ($e instanceof LogoutException) {
                $session = $event->getRequest()->hasSession() ? $event->getRequest()->getSession() : null;
                if ($session instanceof FlashBagAwareSessionInterface) {
                    $session->getFlashBag()->add('error', 'Le lien de déconnexion avait expiré. Cliquez de nouveau sur « Se déconnecter » depuis cette page.');
                }
                $event->setResponse(new RedirectResponse($this->urls->generate('app_home')));

                return;
            }
        }
    }
}
