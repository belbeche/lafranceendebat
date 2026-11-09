<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Commande console "PurgeLoginHistoryCommand".
 *
 * Lancer : php bin/console app:logins:purge
 */
#[AsCommand(name: 'app:logins:purge', description: "Supprime l'historique de connexion de plus de 90 jours")]
class PurgeLoginHistoryCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $n = $this->em->createQuery('DELETE FROM App\Entity\LoginEvent e WHERE e.at < :d')->setParameter('d', new \DateTimeImmutable('-90 days'))->execute();
        $output->writeln("$n ligne(s) supprimee(s).");

        return Command::SUCCESS;
    }
}
