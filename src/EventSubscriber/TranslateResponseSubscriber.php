<?php

namespace App\EventSubscriber;

use App\Service\HtmlTranslator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Traduit toutes les pages HTML publiques quand la langue choisie n'est pas le francais. */
final class TranslateResponseSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly HtmlTranslator $translator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -64]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $response = $event->getResponse();
        $locale = $request->getLocale();
        $type = (string) $response->headers->get('Content-Type', '');
        if ($locale === 'fr' || !str_contains($type, 'text/html') || $response->isEmpty() || $response instanceof \Symfony\Component\HttpFoundation\StreamedResponse || $response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return;
        }
        // L'administration reste en francais (equipe de moderation).
        if (str_starts_with($request->getPathInfo(), '/admin')) {
            return;
        }
        $content = $response->getContent();
        if ($content === false || $content === '') {
            return;
        }
        $response->setContent($this->translator->translate($content, $locale));
        $response->headers->set('Content-Language', $locale);
        $response->setVary(['Cookie', 'Accept-Language'], false);
    }
}
