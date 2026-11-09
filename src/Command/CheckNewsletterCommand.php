<?php
// src/Command/CheckNewsletterCommand.php

namespace App\Command;

use App\Entity\NewsletterCampaign;
use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterCampaignRepository;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:newsletter:check',
    description: 'Check newsletter campaigns and subscribers',
)]
/**
 * Commande console "CheckNewsletterCommand".
 *
 * Lancer : php bin/console app:newsletter:check. Check newsletter campaigns and subscribers
 */
class CheckNewsletterCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private NewsletterCampaignRepository $campaignRepository;
    private NewsletterSubscriberRepository $subscriberRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        NewsletterCampaignRepository $campaignRepository,
        NewsletterSubscriberRepository $subscriberRepository
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->campaignRepository = $campaignRepository;
        $this->subscriberRepository = $subscriberRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Newsletter System Check');

        // 1. Vérifier les campagnes
        $campaigns = $this->campaignRepository->findAll();
        $io->section('Campaigns');
        $io->text(sprintf('Found %d campaigns', count($campaigns)));

        $tableData = [];
        foreach ($campaigns as $campaign) {
            // Vérifier la relation avec les abonnés
            $subscribers = [];
            if (method_exists($campaign, 'getSubscribers')) {
                $subscribers = $campaign->getSubscribers();
            }
            
            $tableData[] = [
                $campaign->getId(),
                substr($campaign->getSubject(), 0, 30),
                $campaign->getStatus(),
                $campaign->getTotalRecipients(),
                count($subscribers),
                $campaign->getSentCount(),
                $campaign->getFailedCount(),
            ];
        }

        $io->table(
            ['ID', 'Subject', 'Status', 'Total', 'Subscribers', 'Sent', 'Failed'],
            $tableData
        );

        // 2. Vérifier les abonnés
        $io->section('Subscribers');
        $allSubscribers = $this->subscriberRepository->findAll();
        $activeSubscribers = $this->subscriberRepository->findBy(['isActive' => true]);
        
        $io->text(sprintf('Total subscribers: %d', count($allSubscribers)));
        $io->text(sprintf('Active subscribers: %d', count($activeSubscribers)));

        // 3. Vérifier la configuration
        $io->section('Configuration Check');
        
        // Vérifier si la relation existe
        $campaign = $this->campaignRepository->find(5);
        if ($campaign) {
            $io->text('Checking campaign #5:');
            $io->text('  - Class: ' . get_class($campaign));
            
            if (method_exists($campaign, 'getSubscribers')) {
                $subscribers = $campaign->getSubscribers();
                $io->text('  - getSubscribers() method exists');
                $io->text('  - Subscribers count: ' . count($subscribers));
                
                if (count($subscribers) > 0) {
                    foreach ($subscribers as $subscriber) {
                        $io->text('    - ' . $subscriber->getEmail());
                    }
                }
            } else {
                $io->warning('  - getSubscribers() method does NOT exist');
            }
        }

        // 4. Vérifier les premiers abonnés actifs
        $io->section('First 5 Active Subscribers');
        $firstActive = $this->subscriberRepository->findBy(
            ['isActive' => true],
            ['id' => 'ASC'],
            5
        );
        
        foreach ($firstActive as $subscriber) {
            $io->text(sprintf('  - %s (ID: %d)', $subscriber->getEmail(), $subscriber->getId()));
        }

        $io->success('Check completed');
        return Command::SUCCESS;
    }
}