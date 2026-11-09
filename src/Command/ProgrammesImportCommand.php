<?php

namespace App\Command;

use App\Service\Programme\AssembleeImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Importe les propositions de loi de l'Assemblée nationale (données ouvertes, licence ouverte Etalab).
 *
 *   php bin/console app:programmes:import --legislature=17
 *   php bin/console app:programmes:import --from-dir=/chemin/deja/decompresse   (sans téléchargement)
 *
 * Sans --from-dir, télécharge les deux jeux officiels dans var/programmes/ (environ 16 Mo), les décompresse et les lit.
 * À planifier une fois par semaine : la commande est idempotente.
 */
#[AsCommand(name: 'app:programmes:import', description: "Importe les propositions de loi de l'Assemblée nationale par groupe politique")]
class ProgrammesImportCommand extends Command
{
    private const BASE = 'https://data.assemblee-nationale.fr/static/openData/repository/%d';
    private const DOCS = '/loi/dossiers_legislatifs/Dossiers_Legislatifs.json.zip';
    private const AMO = '/amo/tous_acteurs_mandats_organes_xi_legislature/AMO30_tous_acteurs_tous_mandats_tous_organes_historique.json.zip';

    public function __construct(private readonly AssembleeImporter $importer, private readonly HttpClientInterface $http, #[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('legislature', null, InputOption::VALUE_REQUIRED, 'Numéro de législature', '17')
            ->addOption('from-dir', null, InputOption::VALUE_REQUIRED, 'Dossier contenant déjà json/document, json/acteur et json/organe')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal de textes (essai)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $leg = (int) $input->getOption('legislature');
        $dir = $input->getOption('from-dir');
        if (!$dir) {
            $dir = $this->projectDir . '/var/programmes';
            @mkdir($dir . '/docs', 0775, true);
            @mkdir($dir . '/amo', 0775, true);
            foreach ([[self::DOCS, 'docs'], [self::AMO, 'amo']] as [$path, $name]) {
                $zip = $dir . '/' . $name . '.zip';
                $io->text('Téléchargement ' . $name . '…');
                $r = $this->http->request('GET', sprintf(self::BASE, $leg) . $path, ['timeout' => 120]);
                if (200 !== $r->getStatusCode()) {
                    $io->error('Téléchargement impossible (HTTP ' . $r->getStatusCode() . ').');

                    return Command::FAILURE;
                }
                file_put_contents($zip, $r->getContent());
                $z = new \ZipArchive();
                if (true !== $z->open($zip)) {
                    $io->error('Archive illisible : ' . $name);

                    return Command::FAILURE;
                }
                $z->extractTo($dir . '/' . $name);
                $z->close();
            }
            $docDir = $dir . '/docs/json/document';
            $actorDir = $dir . '/amo/json/acteur';
            $organeDir = $dir . '/amo/json/organe';
        } else {
            $docDir = $dir . '/json/document';
            $actorDir = $dir . '/json/acteur';
            $organeDir = $dir . '/json/organe';
        }
        foreach ([$docDir, $actorDir, $organeDir] as $d) {
            if (!is_dir($d)) {
                $io->error('Dossier introuvable : ' . $d);

                return Command::FAILURE;
            }
        }
        $stats = $this->importer->import($docDir, $actorDir, $organeDir, $leg, $input->getOption('limit') ? (int) $input->getOption('limit') : null);
        $io->success(sprintf('%d créées, %d mises à jour, %d ignorées, %d sans groupe identifiable (non importées).', $stats['created'], $stats['updated'], $stats['skipped'], $stats['noGroup']));

        return Command::SUCCESS;
    }
}
