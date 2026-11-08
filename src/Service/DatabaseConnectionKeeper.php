<?php

// src/Service/DatabaseConnectionKeeper.php
namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service métier « DatabaseConnectionKeeper ».
 */
class DatabaseConnectionKeeper
{
    private $lastPing = 0;
    private $pingInterval = 30; // secondes
    
    public function __construct(
        private LoggerInterface $logger
    ) {}
    
    public function keepAlive(EntityManagerInterface $em): void
    {
        $now = time();
        
        if ($now - $this->lastPing < $this->pingInterval) {
            return;
        }
        
        $connection = $em->getConnection();
        
        try {
            // Méthode 1: Vérification simple
            if (!$this->isConnectionAlive($connection)) {
                $this->reconnect($connection);
            }
            
            // Méthode 2: Ping explicite
            $this->ping($connection);
            
            $this->lastPing = $now;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to keep connection alive: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function isConnectionAlive($connection): bool
    {
        try {
            $connection->executeQuery('SELECT 1')->fetchOne();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function ping($connection): void
    {
        $start = microtime(true);
        $connection->executeQuery('SELECT 1')->fetchOne();
        $duration = microtime(true) - $start;
        
        if ($duration > 1.0) {
            $this->logger->warning(sprintf('Slow database ping: %.2f seconds', $duration));
        }
    }
    
    private function reconnect($connection): void
    {
        $this->logger->info('Reconnecting to database...');
        
        $connection->close();
        sleep(1); // Petit délai avant reconnexion
        $connection->connect();
        
        // Réappliquer les paramètres
        $connection->executeStatement('SET SESSION wait_timeout = 28800');
        $connection->executeStatement('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        
        $this->logger->info('Database reconnected successfully');
    }
}