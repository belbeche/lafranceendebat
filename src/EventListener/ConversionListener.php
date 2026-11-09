<?php

namespace App\EventListener;

use App\Entity\AnonymousContribution;
use App\Entity\ContactApplication;
use App\Entity\InitiativeSupport;
use App\Entity\MissionApplication;
use App\Entity\Proposition;
use App\Entity\User;
use App\Service\Audience\ConversionTracker;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

/** Traduit la création d'une entité en étape de conversion. Pour une nouvelle étape, ajouter un cas ici. */
#[AsDoctrineListener(event: Events::postPersist)]
class ConversionListener
{
    public function __construct(private readonly ConversionTracker $tracker)
    {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $o = $args->getObject();
        match (true) {
            $o instanceof User => $this->tracker->record('inscription'),
            $o instanceof MissionApplication => $this->tracker->record('candidature_mission'),
            $o instanceof ContactApplication => $this->tracker->record('candidature_contact'),
            $o instanceof InitiativeSupport => $this->tracker->record('soutien_initiative'),
            $o instanceof AnonymousContribution => $this->tracker->record('contribution'),
            $o instanceof Proposition => $this->proposition($o, $args),
            default => null,
        };
    }

    private function proposition(Proposition $p, PostPersistEventArgs $args): void
    {
        $this->tracker->record('contribution');
        if ($p->getAuthor()) {
            $n = (int) $args->getObjectManager()->createQuery('SELECT COUNT(x.id) FROM App\Entity\Proposition x WHERE x.author = :a')->setParameter('a', $p->getAuthor())->getSingleScalarResult();
            if ($n <= 1) {
                $this->tracker->record('premiere_contribution');
            }
        }
    }
}
