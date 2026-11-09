<?php

namespace App\Command;

use App\Repository\ServiceOrderRepository;
use App\Service\Payment\MarketplaceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Commande console "ReleaseOrdersCommand".
 *
 * Lancer : php bin/console app:services:liberer. Reverse automatiquement aux prestataires les commandes payees depuis plus de 14 jours sans litige (a lancer chaque jour par cron).
 */
#[AsCommand(name: 'app:services:liberer', description: 'Reverse automatiquement aux prestataires les commandes payees depuis plus de 14 jours sans litige (a lancer chaque jour par cron).')]
class ReleaseOrdersCommand extends Command
{
    public function __construct(private ServiceOrderRepository $orders, private MarketplaceService $market)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $n = 0;
        foreach ($this->orders->dueForRelease(ServiceOrderRepository::AUTO_RELEASE_DAYS) as $o) {
            try {
                $this->market->release($o);
                ++$n;
            } catch (\Throwable $e) {
                $out->writeln('<error>Commande ' . $o->getId() . ' : ' . $e->getMessage() . '</error>');
            }
        }
        $out->writeln("$n commande(s) reversée(s).");

        return Command::SUCCESS;
    }
}
