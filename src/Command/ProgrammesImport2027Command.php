<?php

namespace App\Command;

use App\Entity\PartyProposal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Importe les mesures d'un programme 2027 depuis un fichier JSON préparé à partir du site officiel de la formation.
 *
 * Pourquoi un fichier et pas un robot : les programmes 2027 ne sont pas en données ouvertes, et plusieurs sites
 * officiels publient encore leur programme 2022 ou un simple sommaire. Un humain relit donc la page, résume chaque mesure
 * en une phrase et colle l'adresse de la page. Voir docs-publics/programmes2027/MODELE.json.
 *
 * Règles reprises du reste du module (à garder si on étend) :
 *  - même exigence pour toutes les formations : adresse https du site officiel, édition "2027" déclarée ;
 *  - les sites tiers (agrégateurs, comparateurs) sont refusés comme source ;
 *  - les mesures arrivent masquées : le modérateur les publie depuis l'admin ;
 *  - relancer l'import ne crée pas de doublon (identifiant calculé sur le sigle et le titre).
 * Pour ajouter une règle de contrôle, étendre self::problem() sans toucher au reste.
 */
#[AsCommand(name: 'app:programmes:import-2027', description: 'Importe les mesures d\'un programme 2027 (JSON relu, source officielle obligatoire)')]
class ProgrammesImport2027Command extends Command
{
    /** Sites qui commentent ou notent les programmes : jamais une source de mesure. */
    private const THIRD_PARTY = ['monvote2027.fr', 'tous-les-programmes.fr', 'solutiondemocratique.fr', 'wikipedia.org', 'lesechos.fr', 'lemonde.fr', 'lefigaro.fr', 'ouest-france.fr'];

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::REQUIRED, 'Fichier JSON')->addOption('simulation', null, InputOption::VALUE_NONE, 'Contrôle sans rien enregistrer');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $j = json_decode((string) @file_get_contents((string) $in->getArgument('fichier')), true);
        if (!is_array($j)) {
            $out->writeln('<error>Fichier illisible.</error>');

            return Command::FAILURE;
        }
        if ($p = self::problem($j)) {
            $out->writeln('<error>' . $p . '</error>');

            return Command::FAILURE;
        }
        $added = $skipped = 0;
        foreach ($j['measures'] as $m) {
            $title = trim((string) ($m['title'] ?? ''));
            $url = trim((string) ($m['source_url'] ?? $j['source_url']));
            if (mb_strlen($title) < 8 || null !== self::hostProblem($url)) {
                $out->writeln('Mesure ignorée (titre trop court ou source refusée) : ' . mb_substr($title, 0, 60));
                ++$skipped;
                continue;
            }
            $ext = 'p2027-' . substr(sha1(mb_strtolower($j['group_code'] . '|' . $title)), 0, 20);
            if ($this->em->getRepository(PartyProposal::class)->findOneBy(['externalId' => $ext])) {
                ++$skipped;
                continue;
            }
            $p = (new PartyProposal())->setSource(PartyProposal::SOURCE_MANUAL)->setExternalId($ext)
                ->setGroup((string) $j['group_code'], (string) $j['group_name'], $j['group_color'] ?? null)
                ->setTitle($title)->setSummary($m['summary'] ?? null)->setTheme($m['theme'] ?? null)->setSourceUrl($url)->setPublished(false);
            if (!$in->getOption('simulation')) {
                $this->em->persist($p);
            }
            ++$added;
        }
        $this->em->flush();
        $out->writeln(sprintf('%d mesure(s) %s, %d ignorée(s). Elles sont masquées : à publier depuis l\'admin.', $added, $in->getOption('simulation') ? 'valides' : 'importées', $skipped));

        return Command::SUCCESS;
    }

    /** @return string|null message d'erreur, null si l'en-tête du fichier est valide */
    public static function problem(array $j): ?string
    {
        // Exception encadrée : une formation qui n'a publié que son programme 2022 peut être présentée comme telle,
        // à condition que le nom du groupe le dise ("2022") et que l'édition soit déclarée honnêtement.
        if (($j['edition'] ?? '') === '2022' && str_contains((string) ($j['group_name'] ?? ''), '2022')) {
            $j['edition'] = '2027';
        }
        if (($j['edition'] ?? '') !== '2027') {
            return 'Le fichier doit déclarer "edition": "2027" (un programme 2022 ne doit pas être présenté comme 2027).';
        }
        if (mb_strlen((string) ($j['group_code'] ?? '')) < 2 || mb_strlen((string) ($j['group_name'] ?? '')) < 3) {
            return 'Sigle et nom de la formation obligatoires.';
        }
        if ($h = self::hostProblem((string) ($j['source_url'] ?? ''))) {
            return $h;
        }

        return is_array($j['measures'] ?? null) && $j['measures'] ? null : 'Aucune mesure dans le fichier.';
    }

    public static function hostProblem(string $url): ?string
    {
        if (!preg_match('~^https://[^\s/]+\.[^\s/]+~i', $url)) {
            return 'Adresse https du site officiel obligatoire.';
        }
        $host = preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
        foreach (self::THIRD_PARTY as $d) {
            if ($host === $d || str_ends_with($host, '.' . $d)) {
                return 'Source tierce refusée (' . $d . ') : indiquez le site officiel de la formation.';
            }
        }

        return null;
    }
}
