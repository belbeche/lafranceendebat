<?php

namespace App\Command;

use App\Entity\PartyProposal;
use App\Service\Programme\ProposalAnalyst;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Produit les hypotheses de chiffrage IA (Gemini) des propositions qui n'ont encore ni chiffrage source ni analyse.
 * Par lots et reprenable : chaque passage traite au plus --limit propositions dans --max-seconds, la suivante continue.
 * Les analyses restent INVISIBLES du public tant qu'un moderateur ne les a pas validees (admin > Propositions des groupes).
 */
#[AsCommand(name: 'app:programmes:analyser', description: "Hypotheses de chiffrage et score de fiabilite par Gemini (en attente de validation)")]
class ProgrammesAnalyserCommand extends Command
{
    use LockableTrait;

    public function __construct(private readonly ProposalAnalyst $analyst, private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal de propositions par passage', '10')
            ->addOption('max-seconds', null, InputOption::VALUE_REQUIRED, 'Temps maximal du passage', '90')
            ->addOption('groupe', null, InputOption::VALUE_REQUIRED, 'Seulement ce sigle de groupe')
            ->addOption('refaire', null, InputOption::VALUE_NONE, 'Refaire aussi celles deja analysees (jamais celles deja validees)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->analyst->isEnabled()) {
            $output->writeln('GEMINI_API_KEY absente : aucune analyse. Rien n\'est invente.');

            return Command::SUCCESS;
        }
        if (!$this->lock()) {
            $output->writeln('Une analyse est deja en cours.');

            return Command::SUCCESS;
        }
        $deadline = microtime(true) + max(10, (int) $input->getOption('max-seconds'));
        $qb = $this->em->getRepository(PartyProposal::class)->createQueryBuilder('p')->andWhere('p.published = true')->andWhere('p.costMillions IS NULL')->andWhere('p.aiApproved = false');
        if (!$input->getOption('refaire')) {
            $qb->andWhere('p.aiAnalysis IS NULL');
        }
        if ($g = $input->getOption('groupe')) {
            $qb->andWhere('p.groupCode = :g')->setParameter('g', $g);
        }
        $items = $qb->orderBy('p.id', 'ASC')->setMaxResults(max(1, (int) $input->getOption('limit')))->getQuery()->getResult();
        $n = $costable = $failed = 0;
        foreach ($items as $p) {
            if (microtime(true) > $deadline - 45) { // une analyse peut prendre jusqu'a 40 s
                break;
            }
            $a = $this->analyst->analyse($p);
            if (null === $a) {
                ++$failed;
                continue;
            }
            $p->setAiAnalysis($a, $this->analyst->getModel());
            $this->em->flush();
            ++$n;
            $costable += $a['costable'] ? 1 : 0;
        }
        $left = (int) $this->em->getRepository(PartyProposal::class)->createQueryBuilder('p')->select('COUNT(p.id)')->andWhere('p.published = true')->andWhere('p.costMillions IS NULL')->andWhere('p.aiAnalysis IS NULL')->getQuery()->getSingleScalarResult();
        $output->writeln(sprintf('%d analysee(s) dont %d chiffrable(s), %d en echec ; il en reste %d a analyser. A valider dans l\'admin avant publication.', $n, $costable, $failed, $left));

        return Command::SUCCESS;
    }
}
