<?php

// src/Command/CreateCampaignCommand.php

namespace App\Command;

use App\Entity\NewsletterCampaign;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande console « CreateCampaignCommand ».
 */
class CreateCampaignCommand extends Command
{
    protected static $defaultName = 'app:create-campaign-bulk';
    
    private $entityManager;
    private $subscriberRepository;
    
    public function __construct(
        EntityManagerInterface $entityManager,
        NewsletterSubscriberRepository $subscriberRepository
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->subscriberRepository = $subscriberRepository;
    }
    
    protected function configure()
    {
        $this->setDescription('Create a campaign with bulk subscriber insertion');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Récupérer les paramètres depuis une file d'attente ou base de données
        $campaignData = []; // À remplacer par vos données
        
        $campaign = new NewsletterCampaign();
        // ... configurer la campagne
        
        $this->entityManager->persist($campaign);
        $this->entityManager->flush();
        
        // Insérer tous les abonnés actifs en SQL direct
        $connection = $this->entityManager->getConnection();
        
        $sql = "INSERT INTO newsletter_campaign_newsletter_subscriber 
                (newsletter_campaign_id, newsletter_subscriber_id)
                SELECT ?, id FROM newsletter_subscriber WHERE is_active = 1";
        
        $connection->executeStatement($sql, [$campaign->getId()]);
        
        $io->success(sprintf('Campagne créée avec %d destinataires', $campaign->getTotalRecipients()));
        
        return Command::SUCCESS;
    }
}