<?php

namespace App\Command;

use App\Service\Content\NewsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Importe des actualités depuis un fichier JSON (mêmes champs que l'import de l'administration). Brouillons sauf --publier. */
#[AsCommand(name: 'app:actualites:importer', description: 'Importe des actualités depuis un fichier JSON (brouillons, ou publiées avec --publier si la source https est présente)')]
final class NewsImportCommand extends Command
{
    public function __construct(private readonly NewsService $news)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::REQUIRED, 'Chemin du fichier JSON')
            ->addOption('publier', null, InputOption::VALUE_NONE, 'Publie celles qui ont un lien de source https');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $f = (string) $input->getArgument('fichier');
        if (!is_file($f)) {
            $output->writeln("<error>Fichier introuvable : $f</error>");

            return Command::FAILURE;
        }
        $r = $this->news->importJson((string) file_get_contents($f), (bool) $input->getOption('publier'));
        $output->writeln(sprintf('%d créée(s), %d déjà présente(s).', $r['created'], $r['skipped']));
        foreach ($r['errors'] as $e) {
            $output->writeln("<comment>$e</comment>");
        }

        return Command::SUCCESS;
    }
}
