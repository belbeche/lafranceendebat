<?php

namespace App\Command;

use App\Service\MaintenanceMode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Commande console "MaintenanceCommand".
 *
 * Lancer : php bin/console app:maintenance. Active, désactive ou affiche le mode maintenance (on | off | status)
 */
#[AsCommand(name: 'app:maintenance', description: 'Active, désactive ou affiche le mode maintenance (on | off | status)')]
class MaintenanceCommand extends Command
{
    public function __construct(private readonly MaintenanceMode $maintenance)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'on, off ou status')
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Message affiché aux visiteurs', '')
            ->addOption('minutes', null, InputOption::VALUE_REQUIRED, 'Durée annoncée en minutes (retour prévu)');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        switch ($in->getArgument('action')) {
            case 'on':
                $this->maintenance->enable((string) $in->getOption('message'), $in->getOption('minutes') ? (int) $in->getOption('minutes') : null);
                $out->writeln('Maintenance ACTIVÉE. Les administrateurs connectés peuvent toujours naviguer.');
                break;
            case 'off':
                $this->maintenance->disable();
                $out->writeln('Maintenance désactivée : le site est de nouveau ouvert.');
                break;
            case 'status':
                $s = $this->maintenance->read();
                $out->writeln($s ? 'ACTIVE depuis ' . date('d/m H:i', $s['since']) . ($s['message'] ? ' : ' . $s['message'] : '') : 'Inactive.');
                break;
            default:
                $out->writeln('Action inconnue : on, off ou status.');

                return Command::INVALID;
        }

        return Command::SUCCESS;
    }
}
