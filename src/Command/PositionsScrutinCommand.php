<?php

namespace App\Command;

use App\Entity\PositionStatement;
use App\Service\Positions\ScrutinImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lie une affirmation de la page Positions à des scrutins officiels de l'Assemblée.
 *
 *   php bin/console app:positions:scrutin --chercher "fin de vie"
 *   php bin/console app:positions:scrutin 12 3456 3457     (affirmation n° 12, scrutins 3456 et 3457)
 */
#[AsCommand(name: 'app:positions:scrutin', description: 'Cherche des scrutins officiels, ou enregistre le vote des groupes pour une affirmation')]
class PositionsScrutinCommand extends Command
{
    public function __construct(private readonly ScrutinImporter $importer, private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('affirmation', InputArgument::OPTIONAL, 'Identifiant de l\'affirmation')
            ->addArgument('scrutins', InputArgument::IS_ARRAY, 'Numéros de scrutins')
            ->addOption('chercher', null, InputOption::VALUE_REQUIRED, 'Mots du titre à chercher');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        if ($q = $in->getOption('chercher')) {
            foreach ($this->importer->search((string) $q) as $r) {
                $out->writeln(sprintf('%d  %s  %s  (pour %d, contre %d)  %s', $r['numero'], $r['date'], $r['sort'], $r['pour'], $r['contre'], mb_substr($r['titre'], 0, 140)));
            }

            return Command::SUCCESS;
        }
        $s = $this->em->find(PositionStatement::class, (int) $in->getArgument('affirmation'));
        if (!$s || !$in->getArgument('scrutins')) {
            $out->writeln('<error>Indiquez une affirmation existante et au moins un numéro de scrutin.</error>');

            return Command::FAILURE;
        }
        foreach ($in->getArgument('scrutins') as $n) {
            $out->writeln(sprintf('Scrutin %d : %d groupes enregistrés.', (int) $n, $this->importer->import($s, (int) $n)));
        }

        return Command::SUCCESS;
    }
}
