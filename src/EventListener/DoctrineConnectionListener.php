<?php

// src/EventListener/DoctrineConnectionListener.php
namespace App\EventListener;

use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\Common\EventSubscriber;

/**
 * Écouteur d'événement Symfony « DoctrineConnectionListener ».
 */
class DoctrineConnectionListener implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [Events::postConnect];
    }
    
    public function postConnect(ConnectionEventArgs $args): void
    {
        $connection = $args->getConnection();
        
        // Configurer la connexion pour rester active
        $connection->executeStatement("SET SESSION wait_timeout = 28800");
        $connection->executeStatement("SET SESSION interactive_timeout = 28800");
    }
}