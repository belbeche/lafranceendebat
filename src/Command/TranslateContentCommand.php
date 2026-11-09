<?php

namespace App\Command;

use App\Entity\Revendication;
use App\Repository\ContentTranslationRepository;
use App\Service\ContentTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Commande console "TranslateContentCommand".
 *
 * Lancer : php bin/console app:contenu:traduire. Traduit les contenus publics (revendications, propositions, fils, services) dans toutes les langues du site.
 */
#[AsCommand(name: 'app:contenu:traduire', description: 'Traduit les contenus publics (revendications, propositions, fils, services) dans toutes les langues du site.')]
class TranslateContentCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly ContentTranslationRepository $repo, private readonly ContentTranslator $translator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal de contenus par exécution', '30')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'revendication, proposition, discussion, service_offer ou all', 'all')
            ->addOption('tout', null, InputOption::VALUE_NONE, 'Inclut les contenus non publiés (relecture admin dans toutes les langues)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->translator->enabled()) {
            $output->writeln('Traduction automatique désactivée (TRANSLATE_PROVIDER=none). Les contributeurs peuvent traduire à la main (/traduire).');

            return Command::SUCCESS;
        }
        $limit = max(1, (int) $input->getOption('limit'));
        $only = (string) $input->getOption('type');
        $done = 0;
        $calls = 0;
        foreach (ContentTranslator::TYPES as $type => [$class]) {
            if ('all' !== $only && $only !== $type) {
                continue;
            }
            $qb = $this->em->getRepository($class)->createQueryBuilder('e')->orderBy('e.id', 'DESC');
            $input->getOption('tout') || $this->onlyPublic($qb, $type);
            foreach ($qb->setMaxResults(200)->getQuery()->toIterable() as $e) {
                if ($done >= $limit) {
                    break 2;
                }
                $have = $this->repo->localesFor($type, (int) $e->getId());
                $touched = false;
                foreach (Revendication::LOCALES as $loc) {
                    if (in_array($loc, $have, true) || $loc === ($this->translator->sourceLocale($e) ?? '') || !$this->translator->supports($loc)) {
                        continue;
                    }
                    try {
                        $this->translator->autoTranslate($e, $loc);
                        ++$calls;
                        $touched = true;
                    } catch (\Throwable $ex) {
                        if (429 === $ex->getCode()) {
                            $output->writeln("<comment>{$ex->getMessage()}</comment>");
                            $output->writeln("$done contenu(x) traduit(s) avant l'arrêt.");

                            return Command::SUCCESS;
                        }
                        $output->writeln("<error>$type #{$e->getId()} $loc : {$ex->getMessage()}</error>");
                    }
                }
                $touched && ++$done;
            }
        }
        $output->writeln("$done contenu(x) traduit(s), $calls appel(s) ({$this->translator->provider()}).");

        return Command::SUCCESS;
    }

    private function onlyPublic(\Doctrine\ORM\QueryBuilder $qb, string $type): void
    {
        match ($type) {
            'revendication' => $qb->andWhere('e.isPublished = true'),
            'proposition' => $qb->andWhere('e.status = :s')->setParameter('s', 'Validée'),
            'service_offer' => $qb->andWhere('e.status = :s')->setParameter('s', 'Publiée'),
            'discussion' => $qb->andWhere('e.status = :s')->setParameter('s', 'Validée'),
            default => null,
        };
    }
}
