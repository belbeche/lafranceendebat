<?php

// src/Command/FixCampaignRelationsCommand.php
namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Réparation ponctuelle des liens entre campagnes et abonnés après un import incomplet. À supprimer une fois
 * les données saines.
 */
class FixCampaignRelationsCommand extends Command
{
    protected static $defaultName = 'app:fix-campaign-relations';

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conn = $this->em->getConnection();
        
        // Désactiver les contraintes temporairement
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        
        // Corriger les import_log
        $conn->executeStatement('
            UPDATE import_log il 
            LEFT JOIN newsletter_campaign nc ON il.campaign_id = nc.id
            SET il.campaign_id = NULL 
            WHERE il.campaign_id IS NOT NULL AND nc.id IS NULL
        ');
        
        // Corriger les email_tracking
        $conn->executeStatement('
            UPDATE email_tracking et 
            LEFT JOIN newsletter_campaign nc ON et.campaign_id = nc.id
            SET et.campaign_id = NULL 
            WHERE et.campaign_id IS NOT NULL AND nc.id IS NULL
        ');
        
        // Corriger les campaign_tracking
        $conn->executeStatement('
            UPDATE campaign_tracking ct 
            LEFT JOIN newsletter_campaign nc ON ct.campaign_id = nc.id
            SET ct.campaign_id = NULL 
            WHERE ct.campaign_id IS NOT NULL AND nc.id IS NULL
        ');
        
        // Réactiver les contraintes
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        
        $output->writeln('Campaign relations fixed!');
        
        return Command::SUCCESS;
    }
}