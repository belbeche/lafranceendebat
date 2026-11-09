<?php

namespace App\Command;

use App\Service\Programme\SenatImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Importe les propositions de loi du Senat (data.senat.fr, licence ouverte). Idempotente : a planifier chaque semaine.
 *   php bin/console app:programmes:import-senat --since-session=2023
 */
#[AsCommand(name: 'app:programmes:import-senat', description: 'Importe les propositions de loi du Sénat par groupe (premier signataire en exercice)')]
class ProgrammesImportSenatCommand extends Command
{
    private const PPL = 'https://data.senat.fr/data/dosleg/ppl.csv';
    private const SENATORS = 'https://data.senat.fr/data/senateurs/ODSEN_GENERAL.csv';

    public function __construct(private readonly SenatImporter $importer, private readonly HttpClientInterface $http)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('since-session', null, InputOption::VALUE_REQUIRED, 'Première session à importer (année de début)', '2023')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal de textes (essai)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $ppl = $this->http->request('GET', self::PPL, ['timeout' => 60])->getContent();
            $sen = $this->http->request('GET', self::SENATORS, ['timeout' => 60])->getContent();
        } catch (\Throwable $e) {
            $io->error('Téléchargement impossible : ' . $e->getMessage());

            return Command::FAILURE;
        }
        $s = $this->importer->import($ppl, $sen, (int) $input->getOption('since-session'), $input->getOption('limit') ? (int) $input->getOption('limit') : null);
        $io->success(sprintf('%d créées, %d mises à jour, %d hors périmètre, %d sans premier signataire en exercice (non importées).', $s['created'], $s['updated'], $s['skipped'], $s['noGroup']));

        return Command::SUCCESS;
    }
}
