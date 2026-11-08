<?php

namespace App\DataFixtures;

use App\Entity\Event;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;


/**
 * Jeu de données de démonstration « AppFixtures ».
 */
class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $event = new Event();
        $event->setName('Notre grand événement d\'ouverture');
        $event->setDescription('Restez connectez et faites des dons caritatives 66% de déduction');
        $event->setDate(new \DateTime('+62 days'));

        $manager->persist($event);
        $manager->flush();
    }
}
