<?php
// src/Command/CleanupBlockedIpsCommand.php

namespace App\Command;

use App\Repository\BlockedIpRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande console « CleanupBlockedIpsCommand ».
 */
class CleanupBlockedIpsCommand extends Command
{
    protected static $defaultName = 'app:cleanup-blocked-ips';
    
    private BlockedIpRepository $blockedIpRepository;

    public function __construct(BlockedIpRepository $blockedIpRepository)
    {
        parent::__construct();
        $this->blockedIpRepository = $blockedIpRepository;
    }

    protected function configure(): void
    {
        $this->setDescription('Nettoie les blocages d\'IP expirés');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $count = $this->blockedIpRepository->cleanupExpired();
        
        $io->success("{$count} blocages expirés ont été supprimés.");
        
        return Command::SUCCESS;
    }
}