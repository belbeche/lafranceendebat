<?php

namespace App\Command;

use App\Entity\Thesis;
use App\Service\Positions\ThesisWriter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Crée ou met à jour une analyse à partir d'un fichier JSON (voir docs-publics/theses/).
 * Sans option : le texte du fichier est enregistré tel quel (rédigé par l'éditeur).
 * Avec --ia : Gemini réécrit l'analyse depuis les sources du fichier, avec recherche web ; elle reste masquée jusqu'à approbation.
 * Avec --publier : publie directement une analyse rédigée par l'éditeur (jamais une analyse de l'IA).
 */
#[AsCommand(name: 'app:theses:rediger', description: 'Crée ou met à jour une analyse sourcée (budget, sources, évolution, nécessaire)')]
class ThesesRedigerCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly ThesisWriter $writer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::REQUIRED)->addOption('ia', null, InputOption::VALUE_NONE, 'Faire rédiger par Gemini')->addOption('publier', null, InputOption::VALUE_NONE, 'Publier (analyse de l\'éditeur seulement)');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $j = json_decode((string) @file_get_contents((string) $in->getArgument('fichier')), true);
        if (!is_array($j) || !preg_match('/^[a-z0-9-]{3,120}$/', (string) ($j['slug'] ?? '')) || empty($j['sources'])) {
            $out->writeln('<error>Fichier invalide : slug, title et sources obligatoires.</error>');

            return Command::FAILURE;
        }
        $ai = $in->getOption('ia');
        if ($ai) {
            $r = $this->writer->write(['title' => (string) $j['title'], 'question' => (string) ($j['summary'] ?? ''), 'sources' => $j['sources']]);
            if (null === $r) {
                $out->writeln('<error>Gemini indisponible ou réponse sans source vérifiable : rien n\'est modifié.</error>');

                return Command::FAILURE;
            }
            $j['summary'] = $r['summary'] ?: ($j['summary'] ?? '');
            $j['sections'] = $r['sections'] + ['manques' => []];
            $j['manques'] = $r['manques'];
        } else {
            $j['manques'] = $j['sections']['manques'] ?? $j['manques'] ?? [];
            unset($j['sections']['manques']);
        }
        $t = $this->em->getRepository(Thesis::class)->findOneBy(['slug' => $j['slug']]) ?? new Thesis($j['slug'], (string) $j['title']);
        $t->setTitle((string) $j['title'])->setTag((string) ($j['tag'] ?? ''))->setData($j, (bool) $ai);
        $t->setApproved(!$ai && $in->getOption('publier'));
        $this->em->persist($t);
        $this->em->flush();
        $out->writeln(sprintf('Analyse « %s » enregistrée (%s, %s).', $j['slug'], $ai ? 'rédigée par l\'IA' : 'rédigée par l\'éditeur', $t->isApproved() ? 'publiée' : 'en attente de validation'));

        return Command::SUCCESS;
    }
}
