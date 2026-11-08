<?php

namespace App\EventSubscriber;

use App\Service\ContentTranslator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Traduction automatique sans cron : un contenu lu dans une langue sans traduction est traduit juste après l'envoi de la page.
 * Garde-fous : 3 traductions par requête, pause d'une heure après un échec, pause de 6 h quand le quota gratuit est épuisé.
 */
class AutoTranslateSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ContentTranslator $translator, private readonly CacheInterface $cache, private readonly LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => 'onTerminate'];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if (!$event->getRequest()->isMethod('GET')) {
            return;
        }
        $queue = $this->translator->drainQueue();
        if (!$queue || $this->cache->get('autotr_quota', fn (ItemInterface $i) => (function () use ($i) { $i->expiresAfter(1); return false; })())) {
            return;
        }
        $done = 0;
        foreach ($queue as $key => [$entity, $locale]) {
            if ($done >= 3) {
                break;
            }
            $guard = 'autotr_' . md5($key);
            if ($this->cache->get($guard, function (ItemInterface $i) { $i->expiresAfter(1); return false; })) {
                continue;
            }
            try {
                $this->translator->autoTranslate($entity, $locale);
                ++$done;
            } catch (\Throwable $e) {
                $this->cache->delete($guard);
                $this->cache->get($guard, function (ItemInterface $i) { $i->expiresAfter(3600); return true; });
                if (429 === $e->getCode()) {
                    $this->cache->delete('autotr_quota');
                    $this->cache->get('autotr_quota', function (ItemInterface $i) { $i->expiresAfter(21600); return true; });
                    $this->logger->notice('Traduction automatique en pause : quota gratuit atteint.');
                    break;
                }
                $this->logger->warning('Traduction automatique : ' . $e->getMessage());
            }
        }
    }
}
