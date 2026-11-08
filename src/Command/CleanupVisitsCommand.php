<?php
// src/Command/CleanupVisitsCommand.php

namespace App\Command;

use App\Service\VisitorTrackerService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande console « CleanupVisitsCommand ».
 */
class CleanupVisitsCommand extends Command
{
    protected static $defaultName = 'app:cleanup-visits';
    protected static $defaultDescription = 'Nettoie les anciennes visites du site';

    private VisitorTrackerService $visitorTracker;

    public function __construct(VisitorTrackerService $visitorTracker)
    {
        parent::__construct();
        $this->visitorTracker = $visitorTracker;
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $deletedCount = $this->visitorTracker->cleanupOldVisits();
            
            $io->success(sprintf('%d anciennes visites ont été supprimées.', $deletedCount));
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur lors du nettoyage: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}