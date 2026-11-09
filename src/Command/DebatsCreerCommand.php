<?php

namespace App\Command;

use App\Entity\Debat;
use App\Entity\Proposition;
use App\Service\DossierCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Cree un debat de la redaction depuis un fichier JSON relu (title, theme, summary, facts, authority).
 *   php bin/console app:debats:creer docs-publics/debats/spector-choc-liberal-egalitaire.json --ouvrir
 * Sans --ouvrir, le debat est cree en brouillon. Idempotent : un debat de meme titre n'est jamais recree.
 */
#[AsCommand(name: 'app:debats:creer', description: "Crée (et ouvre) un débat de la rédaction depuis un fichier JSON relu")]
class DebatsCreerCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly SluggerInterface $slugger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::REQUIRED, 'Fichier JSON')->addOption('ouvrir', null, InputOption::VALUE_NONE, 'Ouvre le débat tout de suite');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $j = json_decode((string) @file_get_contents((string) $input->getArgument('fichier')), true);
        if (!is_array($j)) {
            $output->writeln('<error>Fichier illisible ou JSON invalide.</error>');

            return Command::FAILURE;
        }
        $title = mb_substr(trim((string) ($j['title'] ?? '')), 0, 180);
        $theme = (string) ($j['theme'] ?? '');
        $summary = trim((string) ($j['summary'] ?? ''));
        $facts = trim((string) ($j['facts'] ?? ''));
        if (mb_strlen($title) < 10 || !in_array($theme, Proposition::THEMES, true) || mb_strlen($summary) < 100 || !preg_match('#https://\S+\.\S+#', $facts)) {
            $output->writeln('<error>Titre (10+), thème valide, résumé (100+) et au moins un lien https dans les faits sont obligatoires.</error>');

            return Command::FAILURE;
        }
        $repo = $this->em->getRepository(Debat::class);
        if ($repo->findOneBy(['title' => $title])) {
            $output->writeln('Ce débat existe déjà : rien à faire.');

            return Command::SUCCESS;
        }
        $base = mb_substr(strtolower((string) $this->slugger->slug($title)), 0, 170) ?: 'debat';
        $slug = $base;
        for ($n = 1; $repo->findOneBy(['slug' => $slug]); ) {
            $slug = $base . '-' . (++$n);
        }
        $d = (new Debat())->setTitle($title)->setSlug($slug)->setTheme($theme)->setSummary($summary)->setFacts($facts)
            ->setCompetentAuthority(($j['authority'] ?? null) ?: null)->setDossierSlug(DossierCatalog::starterSlug($theme))
            ->setStatus($input->getOption('ouvrir') ? Debat::STATUS_OPEN : Debat::STATUS_DRAFT);
        $this->em->persist($d);
        $this->em->flush();
        $output->writeln(sprintf('Débat %s : /debats/%s', $input->getOption('ouvrir') ? 'ouvert' : 'créé en brouillon', $slug));

        return Command::SUCCESS;
    }
}
