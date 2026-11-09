<?php

namespace App\Command;

use App\Entity\Debat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Débats de fond posés comme questions neutres et sourcées. Créés en brouillon : un modérateur relit avant ouverture (--publier pour ouvrir). */
#[AsCommand(name: 'app:seed:debats-sensibles', description: 'Crée des débats neutres (Agenda 2030, nouvel ordre mondial, laïcité et religions, dissolution, IA à l\'école) en brouillon.')]
class SeedSensitiveDebatesCommand extends Command
{
    private const DEBATES = [
        ['agenda-2030-quelle-place-dans-les-politiques-francaises', 'Agenda 2030 : quelle place dans les politiques publiques françaises ?', 'Démocratie et dialogue',
            "L'Agenda 2030 est un cadre adopté par les États membres de l'ONU en 2015, avec 17 objectifs de développement durable. Il n'est pas juridiquement contraignant, mais il inspire des politiques nationales et locales. Certains y voient un cadre utile de coopération, d'autres craignent une perte de marge de décision nationale ou critiquent des objectifs jugés trop vagues. La question est de savoir comment un pays doit s'en servir, et avec quel contrôle démocratique.",
            "Cadre adopté en 2015 par l'Assemblée générale de l'ONU : https://unric.org/fr/agenda-2030/developpement-durable/ . Présentation officielle française et suivi : https://www.statistiques.developpement-durable.gouv.fr/le-suivi-international-des-objectifs-de-developpement-durable-odd-de-lagenda-2030", 'Gouvernement, Parlement'],
        ['nouvel-ordre-mondial-que-dit-la-recherche-sur-cette-theorie', 'Nouvel ordre mondial : que sait-on, et que relève de la théorie du complot ?', 'Démocratie et dialogue',
            "L'expression « nouvel ordre mondial » désigne deux choses différentes : un usage diplomatique (la réorganisation des relations entre États après un bouleversement majeur) et une théorie du complot qui prétend qu'une élite secrète gouverne le monde. Ce débat sert à distinguer ce qui est documenté (institutions, traités, influence des lobbies, transparence) de ce qui ne l'est pas, et à apprendre à vérifier une information avant de la partager.",
            "Notice encyclopédique : https://fr.wikipedia.org/wiki/Nouvel_ordre_mondial_(th%C3%A9orie_du_complot) . Registre officiel des représentants d'intérêts (HATVP) : https://www.hatvp.fr/", 'Haute Autorité pour la transparence de la vie publique'],
        ['exclusivisme-religieux-comment-vivre-ensemble-sans-hierarchiser-les-croyances', 'Exclusivisme religieux : comment vivre ensemble quand des croyances se déclarent seules vraies ?', 'Démocratie et dialogue',
            "L'exclusivisme religieux est l'idée qu'une religion détient seule la vérité. Il existe, sous des formes variées, dans plusieurs traditions, et cohabite en France avec le principe de laïcité (liberté de conscience, neutralité de l'État, égalité de tous devant la loi). Le débat porte sur ce qui relève de la conviction personnelle, protégée, et ce qui relève d'actes ou de discours que la loi sanctionne. Il ne vise aucun culte en particulier.",
            "Notice encyclopédique : https://fr.wikipedia.org/wiki/Exclusivisme_religieux . Loi du 9 décembre 1905 : https://www.legifrance.gouv.fr/", 'Ministère de l\'Intérieur (bureau des cultes), Défenseur des droits'],
        ['dissolution-de-l-assemblee-nationale-un-outil-a-encadrer', 'Dissolution de l\'Assemblée nationale : un outil à conserver, encadrer ou supprimer ?', 'Démocratie et dialogue',
            "L'article 12 de la Constitution permet au Président de la République de dissoudre l'Assemblée nationale, après consultation du Premier ministre et des présidents des assemblées, sans pouvoir recommencer dans l'année suivant les élections. Ses défenseurs y voient un moyen de rendre la parole au peuple en cas de blocage. Ses critiques y voient un instrument qui peut produire de l'instabilité. La question est de savoir s'il faut le conserver tel quel, l'encadrer ou le supprimer.",
            "Texte de l'article 12 : https://www.conseil-constitutionnel.fr/le-bloc-de-constitutionnalite/texte-integral-de-la-constitution-du-4-octobre-1958-en-vigueur . Présentation : https://fr.wikipedia.org/wiki/Dissolution_de_l%27Assembl%C3%A9e_nationale_(France)", 'Parlement (révision constitutionnelle, article 89)'],
        ['collaborer-plutot-qu-opposer-que-retenir-des-guerres-du-xxe-siecle', 'Collaborer plutôt qu\'opposer : que retenir des guerres du XXe siècle ?', 'Démocratie et dialogue',
            "Les deux guerres mondiales ont été précédées par des années de division, de propagande et de recherche de boucs émissaires. Après 1945, l'Europe a fait un autre choix : coopérer (Plan Marshall en 1948, déclaration Schuman en 1950, traité de l'Élysée en 1963). La question posée : comment appliquer cette leçon aujourd'hui à nos désaccords politiques, sans nier les différences entre courants ?",
            "Dossier de la plateforme : https://lafranceendebat.fr/dossiers/histoire-guerres . Déclaration Schuman du 9 mai 1950 : https://www.touteleurope.eu/ . Mémorial de la Shoah : https://www.memorialdelashoah.org/", 'Éducation nationale, collectivités, associations'],
        ['desinformation-et-propagande-comment-s-en-proteger', 'Désinformation et propagande : comment s\'en protéger ensemble ?', 'École et transmission',
            "Les régimes autoritaires du XXe siècle ont utilisé la propagande, le bouc émissaire et le contrôle de l'information. Aujourd'hui, les réseaux sociaux accélèrent la circulation des rumeurs. Ce débat cherche des moyens concrets de vérifier, de transmettre et de protéger la liberté d'expression : éducation aux médias, rôle du journalisme indépendant, responsabilité des plateformes, limites posées par la loi.",
            "Dossier : https://lafranceendebat.fr/dossiers/histoire-guerres . Lois du 1er juillet 1972 et du 13 juillet 1990 : https://www.legifrance.gouv.fr/", 'Ministère de la Culture, Arcom, éducation nationale'],
        ['project-meridian-intelligence-artificielle-et-armes-du-futur-quelles-limites', 'Project Meridian : quelles limites pour l\'IA et l\'autonomie dans les armes du futur ?', 'Défense et résilience',
            "Le 30 septembre 2026, le secrétaire américain à la Défense Pete Hegseth a annoncé le « Project Meridian », une étude de 120 jours sur les technologies de la guerre future, codirigée par Elon Musk, Palmer Luckey et Newt Gingrich, ainsi qu'un nouveau commandement militaire de la guerre autonome. Certains y voient une nécessité de défense face à des rivaux, d'autres redoutent une course aux armements et la délégation de décisions létales à des machines. Que doivent défendre la France et l'Europe : des règles internationales, une industrie souveraine, ou les deux ?",
            "Article de presse (à vérifier) : https://www.lejdd.fr/International/projet-meridian-le-pentagone-confie-a-elon-musk-une-mission-sur-les-guerres-du-futur-184878 . Article TV5 Monde : https://information.tv5monde.com/international/etats-unis-elon-musk-embauche-par-le-ministere-de-la-guerre-pour-travailler-sur-les-armes-du-futur-2839826 . Nations unies, armes autonomes : https://www.un.org/fr/", 'Ministère des Armées, Parlement, ONU'],
        ['superintelligence-artificielle-faut-il-arreter-la-course', 'Superintelligence artificielle : faut-il arrêter la course ?', 'Industrie et recherche',
            "Une superintelligence est un système hypothétique dont les capacités dépasseraient largement celles des meilleurs esprits humains. En octobre 2025, des centaines de scientifiques, personnalités et responsables, dont des figures de l'IA moderne, ont appelé à stopper la course à son développement tant qu'il n'existe pas de consensus scientifique sur la sécurité et un soutien public large. D'autres estiment qu'une interdiction est irréaliste, ou freinerait la recherche médicale et scientifique. Ce débat prolonge celui sur l'IA à l'école : comment former, informer et décider collectivement avant que la technologie ne décide pour nous ?",
            "Définition : https://fr.wikipedia.org/wiki/Superintelligence . Appel d'octobre 2025, article du Monde : https://www.lemonde.fr/pixels/article/2025/10/22/superintelligence-artificielle-des-centaines-d-experts-et-personnalites-dont-des-figures-de-l-ia-moderne-appellent-a-stopper-la-course-au-developpement_6648817_4408996.html . Débat lié : https://lafranceendebat.fr/debats/faut-il-apprendre-a-utiliser-l-intelligence-artificielle-des-l-ecole-2", 'Parlement, Union européenne, ONU'],
        ['guerre-au-moyen-orient-comment-contribuer-a-la-paix-sans-importer-le-conflit', 'Guerre au Moyen-Orient : comment contribuer à la paix sans importer le conflit en France ?', 'Défense et résilience',
            "Le conflit au Moyen-Orient fait des victimes civiles et alimente des tensions jusque dans nos villes. Ce débat n'a pas pour but de désigner un camp, mais de répondre à trois questions : quels faits sont établis par des sources reconnues (ONU, CICR, presse indépendante), quel rôle la diplomatie française et européenne peut jouer, et comment protéger chaque habitant de la haine, qu'elle vise des juifs, des musulmans ou d'autres, sans interdire le débat légitime sur les politiques des États. Toute contribution doit citer ses sources et respecter la charte de paix.",
            "Nations unies : https://www.un.org/fr/ . Comité international de la Croix-Rouge : https://www.icrc.org/fr . Dossier de la plateforme : https://lafranceendebat.fr/dossiers/terrorisme-memoire", 'Ministère de l\'Europe et des Affaires étrangères, ONU'],
        ['terrorisme-en-france-comment-se-souvenir-de-toutes-les-victimes', 'Terrorisme et violences racistes en France : comment se souvenir de toutes les victimes ?', 'Sécurité et réinsertion',
            "L'article 421-1 du Code pénal définit les actes de terrorisme sans tenir compte de l'identité des victimes ni de l'idéologie de l'auteur. Depuis 1982 (rue des Rosiers), la France a connu des attaques et des crimes antisémites, racistes ou religieux, dont l'attaque de Trappes en octobre 2026 selon le parquet national antiterroriste. Certaines déclarations publiques ont présenté des événements comme des « tournants », d'autres rappellent les faits antérieurs. La question posée : comment condamner chaque attaque, nommer le mobile quand la justice l'établit, et préserver une mémoire commune sans hiérarchiser les victimes ni l'utiliser contre des personnes ?",
            "Article 421-1 du Code pénal : https://www.legifrance.gouv.fr/codes/article_lc/LEGIARTI000032751714/2016-07-03 . Dossier : https://lafranceendebat.fr/dossiers/terrorisme-memoire", 'Ministère de la Justice, Parquet national antiterroriste'],
        ['fiscalite-et-attractivite-faut-il-s-inquieter-du-depart-de-millionnaires', 'Fiscalité et attractivité : faut-il s\'inquiéter du départ de millionnaires ?', 'Finances publiques',
            "Selon le Henley Private Wealth Migration Report 2025 (publié le 24 juin 2025 par Henley & Partners avec New World Wealth), la France afficherait un solde de -800 millionnaires en 2025, contre -16 500 au Royaume-Uni, -7 800 en Chine et -400 en Allemagne, et +9 800 aux Émirats, +7 500 aux États-Unis, +3 600 en Italie et +3 000 en Suisse. Ce sont des projections d'un cabinet qui vend des services de migration d'investisseurs, pas un décompte officiel, et le seuil porte sur 1 million de dollars de patrimoine liquide investissable. Faut-il revoir la fiscalité pour retenir les capitaux, ou ces départs restent-ils marginaux ? Quels indicateurs publics (INSEE, DGFiP, Cour des comptes) permettraient de trancher ?",
            "Communiqué Henley & Partners : https://www.henleyglobal.com/newsroom/press-releases/henley-private-wealth-migration-report-2025 . Données publiques : https://www.insee.fr/ . Cour des comptes : https://www.ccomptes.fr/", 'Ministère de l\'Économie, Parlement'],
        ['ia-et-controle-humain-qui-garde-le-dernier-mot', 'IA et contrôle humain : qui garde le dernier mot ?', 'Industrie et recherche',
            "L'article 14 du règlement européen sur l'IA exige une supervision humaine effective des systèmes à haut risque. Dans le même temps, des experts doutent de la fiabilité du code généré par IA, et beaucoup d'organisations automatisent sur des données non vérifiées. Comment organiser, en France, le contrôle humain, la qualité des données et la formation, sans freiner l'innovation ?",
            "Dossier : https://lafranceendebat.fr/dossiers/ia-controle-humain . Règlement (UE) 2024/1689 : https://eur-lex.europa.eu/", 'Union européenne, CNIL, Parlement'],
        ['faut-il-apprendre-a-utiliser-l-intelligence-artificielle-des-l-ecole-2', 'Faut-il apprendre à utiliser l\'intelligence artificielle dès l\'école ?', 'École et transmission',
            "L'intelligence artificielle entre dans la vie quotidienne des élèves. Certains estiment qu'il faut l'enseigner tôt, pour former l'esprit critique et éviter la dépendance. D'autres préfèrent d'abord consolider les savoirs fondamentaux (lecture, écriture, calcul). Ce débat cherche l'équilibre entre transmission des savoirs et maîtrise des outils.",
            "Ministère de l'Éducation nationale : https://www.education.gouv.fr/", 'Ministère de l\'Éducation nationale'],
    ];

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('publier', null, InputOption::VALUE_NONE, 'Ouvre directement les débats (après relecture humaine).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $open = (bool) $input->getOption('publier');
        $created = 0;
        foreach (self::DEBATES as [$slug, $title, $theme, $summary, $facts, $authority]) {
            if ($this->em->getRepository(Debat::class)->findOneBy(['slug' => $slug])) {
                continue;
            }
            $d = (new Debat())->setTitle($title)->setSlug($slug)->setTheme($theme)->setSummary($summary)->setFacts($facts)->setCompetentAuthority($authority)
                ->setStatus($open ? Debat::STATUS_OPEN : Debat::STATUS_DRAFT);
            $this->em->persist($d);
            ++$created;
        }
        $this->em->flush();
        $output->writeln(sprintf('%d débat(s) créé(s) %s.', $created, $open ? 'et ouverts' : 'en brouillon (à relire en modération)'));

        return Command::SUCCESS;
    }
}
