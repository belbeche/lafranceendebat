<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/**
 * Commande console "SessionsCreateTableCommand".
 *
 * Lancer : php bin/console app:sessions:creer-table. Cree la table "sessions" (a lancer une fois avant SESSION_IN_DB=1)
 */
#[AsCommand(name: 'app:sessions:creer-table', description: 'Cree la table "sessions" (a lancer une fois avant SESSION_IN_DB=1)')]
class SessionsCreateTableCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $handler = new PdoSessionHandler($this->connection->getNativeConnection(), ['db_table' => 'sessions']);
        try {
            $handler->createTable();
            $output->writeln('Table "sessions" creee. Ajoutez SESSION_IN_DB=1 dans .env.local puis videz le cache.');
        } catch (\Throwable $e) {
            $output->writeln('Table deja presente ou creation impossible : ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
