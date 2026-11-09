<?php

namespace App\EventListener;

use App\Entity\Debat;
use App\Service\NotificationMailer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/** Previent l'auteur d'un debat quand son statut change, quel que soit l'ecran utilise (gestion, administration). */
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postFlush)]
class DebatStatusListener
{
    /** @var list<array{0: Debat, 1: string}> */
    private array $pending = [];
    private bool $sending = false;

    public function __construct(#[\Symfony\Component\DependencyInjection\Attribute\AutowireLocator([NotificationMailer::class])] private readonly \Psr\Container\ContainerInterface $locator)
    {
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $e = $args->getObject();
        if ($e instanceof Debat && $args->hasChangedField('status')) {
            $this->pending[] = [$e, (string) $args->getOldValue('status')];
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->sending || !$this->pending) {
            return;
        }
        $todo = $this->pending;
        $this->pending = [];
        $this->sending = true;
        try {
            foreach ($todo as [$debat, $old]) {
                $this->locator->get(NotificationMailer::class)->debateStatusChanged($debat, $old);
            }
        } catch (\Throwable) {
        } finally {
            $this->sending = false;
        }
    }
}
