<?php

namespace App\Command;

use App\Entity\Inspiration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Fiches de départ « Ils ont contribué » (idempotent : ne recrée pas une fiche existante). */
#[AsCommand(name: 'app:seed:inspirations', description: 'Ajoute les premières fiches « Ils ont contribué »')]
class SeedInspirationsCommand extends Command
{
    public const ITEMS = [
        ['Michèle Guerrin', 'education', 'À l\'origine d\'Onlineformapro. Le 1er juillet 1999, elle remporte un concours du ministère de l\'Éducation nationale destiné aux entreprises innovantes dans les technologies, avec un projet de formation à distance tutorée.', 'https://www.onlineformapro.com/societe/'],
        ['George C. Marshall', 'reconstruction', 'Secrétaire d\'État américain, il propose en juin 1947 un programme d\'aide à la reconstruction de l\'Europe : le plan Marshall, dont s\'inspire le Plan Marshall citoyen.', 'https://www.marshallfoundation.org/marshall/the-marshall-plan/'],
        ['Jean Monnet', 'reconstruction', 'Premier commissaire au Plan en France, inspirateur de la déclaration Schuman du 9 mai 1950, point de départ de la construction européenne.', 'https://european-union.europa.eu/principles-countries-history/history-eu/1945-59/schuman-declaration-may-1950_fr'],
        ['Elinor Ostrom', 'democratie', 'Prix Nobel d\'économie 2009 pour ses travaux sur la gestion collective des biens communs : des communautés peuvent s\'organiser sans que tout passe par l\'État ou le marché.', 'https://www.nobelprize.org/prizes/economic-sciences/2009/ostrom/facts/'],
        ['Simone Veil', 'democratie', 'Première présidente du Parlement européen élu au suffrage universel direct, en 1979. Figure du débat respectueux sur les sujets les plus sensibles.', 'https://www.europarl.europa.eu/former_ep_presidents/presidents/simone-veil'],
        ['Nicolas de Condorcet', 'education', 'Auteur d\'un rapport sur l\'organisation de l\'instruction publique présenté en 1792 : une instruction ouverte à tous, pour des citoyens éclairés.', 'https://fr.wikipedia.org/wiki/Nicolas_de_Condorcet'],
        ['Jules Ferry', 'education', 'Ministre de l\'Instruction publique, il porte les lois de 1881 et 1882 qui rendent l\'école primaire gratuite, laïque et obligatoire.', 'https://fr.wikipedia.org/wiki/Jules_Ferry'],
        ['Olympe de Gouges', 'droits', 'Autrice de la Déclaration des droits de la femme et de la citoyenne, en 1791, qui réclame l\'égalité des droits.', 'https://fr.wikipedia.org/wiki/Olympe_de_Gouges'],
        ['René Cassin', 'droits', 'Juriste français, l\'un des principaux rédacteurs de la Déclaration universelle des droits de l\'homme de 1948. Prix Nobel de la paix 1968.', 'https://fr.wikipedia.org/wiki/Ren%C3%A9_Cassin'],
        ['Ambroise Croizat', 'social', 'Ministre du Travail en 1945-1947, il met en place la Sécurité sociale et étend les retraites.', 'https://fr.wikipedia.org/wiki/Ambroise_Croizat'],
        ['Pierre Laroque', 'social', 'Haut fonctionnaire, il conçoit le plan de Sécurité sociale de 1945 et dirige sa mise en place.', 'https://fr.wikipedia.org/wiki/Pierre_Laroque'],
        ['Abbé Pierre', 'social', 'Fondateur d\'Emmaüs en 1949, il lance l\'appel de l\'hiver 1954 en faveur des sans-abri.', 'https://fr.wikipedia.org/wiki/Abb%C3%A9_Pierre'],
        ['Louis Pasteur', 'sante', 'Chimiste et biologiste, il met au point en 1885 le vaccin contre la rage et fonde la microbiologie moderne.', 'https://fr.wikipedia.org/wiki/Louis_Pasteur'],
        ['Marie Curie', 'sciences', 'Première personne à recevoir deux prix Nobel : de physique en 1903 et de chimie en 1911, pour ses travaux sur la radioactivité.', 'https://fr.wikipedia.org/wiki/Marie_Curie'],
        ['Tim Berners-Lee', 'numerique', 'Inventeur du World Wide Web au CERN en 1989, il défend un web ouvert et accessible à tous.', 'https://fr.wikipedia.org/wiki/Tim_Berners-Lee'],
        ['Ada Lovelace', 'numerique', 'Mathématicienne britannique du XIXe siècle, auteure de notes sur la machine analytique de Babbage, souvent considérées comme le premier programme informatique.', 'https://fr.wikipedia.org/wiki/Ada_Lovelace'],
        ['Muhammad Yunus', 'economie', 'Fondateur de la Grameen Bank, pionnier du microcrédit. Prix Nobel de la paix 2006.', 'https://fr.wikipedia.org/wiki/Muhammad_Yunus'],
        ['Wangari Maathai', 'ecologie', 'Fondatrice du Mouvement de la ceinture verte au Kenya, qui a mobilisé des citoyens pour planter des millions d\'arbres. Prix Nobel de la paix 2004.', 'https://fr.wikipedia.org/wiki/Wangari_Maathai'],
        ['Rachel Carson', 'ecologie', 'Biologiste américaine, autrice de « Printemps silencieux » (1962), qui alerte sur les effets des pesticides.', 'https://fr.wikipedia.org/wiki/Rachel_Carson'],
        ['Nelson Mandela', 'paix', 'Après vingt-sept ans de prison, il mène la transition démocratique de l\'Afrique du Sud et la commission Vérité et Réconciliation. Prix Nobel de la paix 1993.', 'https://fr.wikipedia.org/wiki/Nelson_Mandela'],
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    public static function seed(EntityManagerInterface $em): int
    {
        $n = 0;
        foreach (self::ITEMS as [$name, $domain, $summary, $src]) {
            if ($em->getRepository(Inspiration::class)->findOneBy(['name' => $name])) {
                continue;
            }
            $i = new Inspiration($name, $domain, $summary, $src);
            $i->publish();
            $em->persist($i);
            ++$n;
        }
        $em->flush();

        return $n;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(self::seed($this->em) . ' fiche(s) ajoutée(s).');

        return Command::SUCCESS;
    }
}
