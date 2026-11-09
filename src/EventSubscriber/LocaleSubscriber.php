<?php

namespace App\EventSubscriber;

use App\Service\Languages;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Langue = cookie de preference (choisie a l'arrivee), sinon langue du navigateur, sinon francais.
 * Aucune donnee personnelle : le cookie ne contient qu'un code de langue valide.
 */
final class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Languages $languages)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => [['onRequest', 20]]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $saved = (string) $request->cookies->get(Languages::COOKIE, '');
        $hasChoice = $this->languages->isSupported($saved);
        $locale = $hasChoice ? $saved : ($this->languages->negotiate($request) ?? Languages::DEFAULT);
        // ?lang=xx : version d'une page dans une langue traduite, pour le referencement (hreflang) et le partage de liens.
        // Prioritaire sur le cookie, sans le modifier ; uniquement en lecture (GET) et pour les langues dont l'interface est traduite.
        $forced = (string) $request->query->get('lang', '');
        if ($request->isMethod('GET') && '' !== $forced && $this->languages->isSupported($forced) && $this->languages->hasUi($forced)) {
            $locale = $forced;
            $hasChoice = true;
            $request->attributes->set('_lang_forced', $forced);
        }

        $request->setLocale($locale);
        // Utilise par la page d'accueil de la langue : propose le choix tant qu'aucune langue n'a ete choisie.
        $request->attributes->set('_lang_prompt', !$hasChoice);
        $request->attributes->set('_lang_detected', $locale);
    }
}
