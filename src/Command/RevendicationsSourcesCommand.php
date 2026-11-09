<?php

namespace App\Command;

use App\Entity\Revendication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Contrôle des sources des revendications.
 *
 *   php bin/console app:revendications:sources                 liste celles sans source https
 *   php bin/console app:revendications:sources slug "Libellé" https://...   renseigne la source d'une revendication
 *
 * Aucune source n'est inventée : la commande n'écrit que ce qu'on lui donne, et refuse une adresse qui n'est pas en https.
 */
#[AsCommand(name: 'app:revendications:sources', description: 'Liste les revendications sans source, ou renseigne la source de l\'une d\'elles')]
class RevendicationsSourcesCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('slug', InputArgument::OPTIONAL)->addArgument('libelle', InputArgument::OPTIONAL)->addArgument('url', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $repo = $this->em->getRepository(Revendication::class);
        if ($slug = $in->getArgument('slug')) {
            $r = $repo->findOneBy(['slug' => $slug]);
            $url = (string) $in->getArgument('url');
            if (!$r || !str_starts_with($url, 'https://')) {
                $out->writeln('<error>Revendication introuvable ou adresse non https.</error>');

                return Command::FAILURE;
            }
            $r->setSourceLabel(mb_substr((string) $in->getArgument('libelle'), 0, 160))->setSourceUrl(mb_substr($url, 0, 500));
            $this->em->flush();
            $out->writeln('Source enregistrée.');

            return Command::SUCCESS;
        }
        $n = 0;
        foreach ($repo->findAll() as $r) {
            if (!str_starts_with((string) $r->getSourceUrl(), 'https://')) {
                $out->writeln(sprintf('%s  %s', $r->getSlug(), $r->getTitle()));
                ++$n;
            }
        }
        $out->writeln(sprintf('%d revendication(s) sans source https.', $n));

        return Command::SUCCESS;
    }
}
