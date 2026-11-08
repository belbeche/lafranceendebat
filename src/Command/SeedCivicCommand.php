<?php

namespace App\Command;

use App\Entity\Debat;
use App\Entity\Revendication;
use App\Entity\Mission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Amorce editoriale : revendications tirees des documents fournis par le projet, a verifier avant publication.
 * Aucune donnee n'est inventee : chaque revendication indique ce qui reste a verifier et sa source documentaire.
 */
#[AsCommand(name: 'app:seed:civic', description: 'Cree les revendications et débats de départ (non publiés tant que non relus).')]
class SeedCivicCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repo = $this->em->getRepository(Revendication::class);
        $items = [
            ['jeunesse-education-plan-urgence', 'Jeunes et étudiants', 'École et transmission', "Plan d'urgence pour l'Éducation nationale : rénovation des écoles et universités, recrutement de personnels d'encadrement (un encadrant pour 500 élèves est réclamé), suppression de la sélection à l'université avec création de 150 000 places supplémentaires.", "Chiffres issus de la plateforme « 10 mesures d'urgence » citée dans le document de contexte. Aucun n'a été vérifié ici : à confirmer auprès de la source primaire avant publication.", 'Ministère de l\'Éducation nationale ; Parlement (loi de finances)'],
            ['jeunesse-reponse-non-uniquement-securitaire', 'Jeunes et étudiants', 'Justice', "Demande que la mobilisation des jeunes ne soit pas traitée uniquement sous l'angle sécuritaire, et que les causes (école, précarité, avenir professionnel, inégalités territoriales) soient examinées.", "Texte d'appel à la jeunesse fourni au projet. Les chiffres d'interpellations qu'il cite ne sont pas vérifiés.", 'Gouvernement ; Parlement (mission d\'information)'],
            ['senat-debat-national-priorites', 'Tous les citoyens', 'Démocratie et dialogue', "Lettre au Sénat : débat public national, mission d'information documentée, auditions des étudiants, travailleurs, agriculteurs, sapeurs-pompiers, soignants, enseignants, indépendants et retraités, publication des conclusions et étude d'une proposition de loi.", "La lettre est une demande citoyenne. Le Sénat n'est tenu d'y répondre par aucune procédure, sauf pétition remplissant les conditions de son règlement.", 'Sénat (Conférence des présidents)'],
            ['pompiers-penibilite-retraite', 'Sapeurs-pompiers et secours', 'Emploi et formation', "Examen parlementaire approfondi des conditions d'exercice et de retraite des sapeurs-pompiers et des métiers à forte pénibilité ou à risque.", "Demande issue de la lettre au Sénat. Pénibilité réelle, exposition aux risques et besoins de recrutement à documenter.", 'Parlement ; ministère du Travail'],
            ['agriculteurs-revenus-transmission', 'Agriculteurs', 'Agriculture et alimentation', "Évaluation globale des difficultés agricoles (revenus, charges, transmission, foncier, concurrence internationale, normes, renouvellement des générations, climat) avec participation directe des agriculteurs aux travaux parlementaires.", "Demande issue de la lettre au Sénat. Les données sectorielles restent à sourcer.", 'Ministère de l\'Agriculture ; Parlement'],
            ['salaries-tarif-agent-energie', 'Salariés et syndicats', 'Emploi et formation', "Maintien du « tarif agent » des électriciens et gaziers, que la mobilisation d'octobre 2026 aurait fait garantir par le gouvernement.", "Fait rapporté par le document de contexte sans source primaire : à vérifier (communiqué officiel ou accord signé) avant toute publication.", 'Gouvernement ; employeurs de la branche'],
            ['prisons-surpopulation-alternatives', 'Justice et personnes détenues', 'Justice', "Réduire la surpopulation carcérale par des alternatives à la prison (bracelet électronique, travail d'intérêt général), tout en soutenant les victimes et en finançant insertion et formation.", "Le document de contexte cite plus de 84 000 détenus pour environ 62 500 places fin 2025 : chiffre à confirmer sur les statistiques officielles du ministère de la Justice.", 'Ministère de la Justice ; Parlement'],
        ];
        // Plan Marshall citoyen (document de travail, octobre 2026). Publiées : ce sont des propositions à instruire, jamais des économies acquises.
        $plan = [
            ['pm-revue-depenses-resultats', 'Revue des dépenses publiques par résultats', 'Mettre en place des revues pluriannuelles de programmes avec coûts complets, résultats, bénéficiaires et comparaison entre territoires.', "Ne pas supprimer un service sur la seule base d'un coût élevé si le besoin social est plus important.", 'Chaque ministère et opérateur, sous contrôle parlementaire et audit indépendant'],
            ['pm-achats-publics-contrats', 'Achats publics et contrats', "Regrouper les achats lorsque cela réduit réellement le coût total, standardiser les données d'achat, évaluer les contrats en coût de cycle de vie et ouvrir l'accès aux PME et à l'ESS.", 'Préserver la concurrence et éviter que les marchés groupés excluent les petits fournisseurs.', "Acheteurs publics, centrales d'achat, collectivités"],
            ['pm-simplification-une-donnee-une-fois', 'Simplification administrative : une donnée demandée une fois', 'Appliquer le principe « une donnée demandée une fois » lorsque la loi le permet, réutiliser les justificatifs et harmoniser les formulaires.', "Pas de suppression des contrôles nécessaires ni d'échanges de données sans base légale (RGPD, cybersécurité).", 'Administrations et financeurs'],
            ['pm-evaluation-aides-economiques', 'Évaluation des aides économiques', "Pour chaque dispositif, publier objectifs, bénéficiaires, coûts de gestion, additionnalité, emplois ou investissements réellement créés, effets d'aubaine et échéance de réexamen.", "Éviter d'attribuer au dispositif toute évolution économique observée.", 'État, régions, opérateurs et évaluateurs indépendants'],
            ['pm-productivite-tpe-pme', 'Productivité des TPE et PME', "Créer un parcours de diagnostic court (processus, énergie, numérique, IA, cybersécurité, compétences), avec aide conditionnée à un plan d'action et à une mesure avant et après.", "Pas de subvention à l'IA sans besoin métier, sécurité et indicateur de valeur.", 'Régions, chambres consulaires, Bpifrance, branches'],
            ['pm-formation-emploi-reel', 'Formation liée à un emploi réel', "Développer les formations construites avec les employeurs, les mises en situation et les formations préalables à l'embauche.", "Ne pas mesurer le succès au seul nombre d'inscriptions ou de certifications.", 'France Travail, employeurs, régions, organismes de formation'],
            ['pm-prevention-sanitaire-sociale', 'Prévention sanitaire et sociale', "Évaluer les actions de prévention et d'accompagnement qui peuvent éviter des crises plus coûteuses.", 'Ne pas prétendre que toute prévention génère automatiquement des économies budgétaires ; protéger les données de santé.', 'Assurance maladie, collectivités, associations, professionnels'],
            ['pm-logement-renovation', 'Logement et rénovation', "Accélérer la rénovation énergétique rentable, l'occupation de logements vacants lorsque c'est faisable, la prévention des expulsions et la coordination des aides.", "Ne pas compter deux fois les économies d'énergie et les gains sociaux.", 'État, collectivités, bailleurs, acteurs sociaux'],
            ['pm-energie-ressources', 'Énergie et ressources', 'Réduire les consommations mesurables dans les bâtiments publics et associatifs ; améliorer maintenance, réglage, achats et suivi.', "Ne pas réduire la température ou l'accès au service au détriment des personnes vulnérables.", 'Gestionnaires de bâtiments et opérateurs spécialisés'],
            ['pm-reemploi-economie-circulaire', 'Réemploi et économie circulaire', 'Développer le réemploi vérifié des équipements, la réparation, la durée de vie et les filières locales, avec traçabilité et effacement sécurisé des données.', 'Garantir sécurité, conformité, assurance et traitement des déchets.', 'Collectivités, ESS, entreprises, filières agréées'],
            ['pm-qualite-emploi-public-associatif', "Qualité de l'emploi public et associatif", "Mesurer postes vacants, heures supplémentaires, absentéisme, turnover et charge administrative avant de conclure qu'un service est surdimensionné ; réaffecter le temps vers le cœur de mission.", "Pas d'économies fondées sur la précarisation ou le transfert de charge vers les familles.", 'Employeurs, représentants du personnel, financeurs'],
            ['pm-transparence-resultats', 'Transparence des résultats', 'Publier un tableau de bord accessible sur dépenses, services rendus, délais, qualité, évaluations et suites données, avec données agrégées et contrôles de confidentialité.', 'Éviter les classements simplistes et protéger les données personnelles.', 'Parlement, administrations, collectivités, évaluateurs'],
            ['pm-ess-diagnostic-couts-mission', 'ESS : diagnostic de coûts et de mission', 'Proposer à 3 à 5 structures ESS volontaires un diagnostic de 6 à 8 semaines (achats, locaux, flotte, assurances, logiciels, reporting financeurs, utilisation des équipements).', "Aucun objectif de réduction uniforme ; les dépenses de sécurité, de qualité d'accompagnement et de protection des salariés ne sont pas ciblées sans analyse.", 'Région Normandie, CRESS Normandie, réseaux ESS'],
            ['pm-ess-achats-groupes-mutualisation', 'ESS : achats groupés et mutualisation volontaires', 'Agréger les besoins récurrents de structures volontaires (informatique, téléphonie, énergie, maintenance) et étudier un service support partagé à adhésion volontaire.', 'Pas de monopole imposé ; vérifier les règles de commande publique ; chaque structure garde sa gouvernance.', "Structures ESS, centrales d'achat"],
            ['pm-ess-subventions-actifs-reinvestissement', 'ESS : allègement du reporting, actifs partagés, réinvestissement encadré', "Harmoniser les justificatifs demandés par plusieurs financeurs, inventorier les actifs sous-utilisés, et convenir à l'avance de l'usage des économies.", "Aucune réaffectation sans accord écrit du financeur ; un reliquat n'est jamais libre par défaut ; pas de collecte de fonds sans cadre juridique.", 'Financeurs, organes de gouvernance des structures'],
        ];
        // Lettre ouverte au Sénat « Pour un débat national et une consultation citoyenne sur les priorités de la France » (texte fourni au projet).
        $lettre = [
            ['lettre-senat-1-jeunesse-etudiants', 'Jeunes et étudiants', 'École et transmission', 'Entendre la jeunesse et les étudiants', "Examiner dans un cadre national les préoccupations des jeunes (accès aux études, coût de la vie, logement, emploi, précarité, conditions d'apprentissage, perspectives), avec étudiants, lycéens, enseignants, établissements, familles et pouvoirs publics.", 'Chiffrer chaque préoccupation avec des sources publiques (enquêtes, données ministérielles) avant publication.', 'Parlement, ministères chargés de l\'enseignement'],
            ['lettre-senat-2-moyens-services-publics', 'Tous les citoyens', 'Finances publiques', 'Garantir les moyens des services publics', "Évaluation publique et transparente des besoins réels en effectifs et en matériels dans les services essentiels (santé, sécurité, éducation, secours, justice, transports, services sociaux, services territoriaux), pour déterminer besoins, financements nécessaires et priorités nationales.", 'Aucun chiffre avancé sans source : renvoyer aux rapports de la Cour des comptes, de l\'INSEE et des ministères.', 'Parlement, Cour des comptes'],
            ['lettre-senat-3-inflation-pouvoir-achat', 'Tous les citoyens', 'Finances publiques', "Prendre en compte l'inflation et le pouvoir d'achat", "Évaluation nationale de l'évolution du coût de la vie et de ses effets sur les groupes sociaux et professionnels, distinguant difficultés structurelles et conjoncturelles, et identifiant des mesures durables sans fragiliser les finances publiques.", 'Données de référence : INSEE (indice des prix, pouvoir d\'achat par catégorie).', 'Gouvernement, Parlement, INSEE'],
            ['lettre-senat-4-penibilite-risques', 'Sapeurs-pompiers et secours', 'Emploi et formation', 'Reconnaître les métiers à forte pénibilité et à risque', "Travail parlementaire approfondi sur les conditions d'exercice et les perspectives de retraite des sapeurs-pompiers et des autres professions exposées, en tenant compte de la pénibilité réelle, de l'exposition aux risques, des horaires, de la durée des carrières et des besoins de recrutement.", 'Citer le droit en vigueur (code de la sécurité sociale, régimes spéciaux) avec source avant toute comparaison.', 'Parlement, ministères concernés'],
            ['lettre-senat-5-monde-agricole', 'Agriculteurs', 'Agriculture et alimentation', 'Soutenir durablement le monde agricole', "Évaluation globale des difficultés agricoles (revenus, charges, transmission, foncier, concurrence internationale, normes, conditions de travail, renouvellement des générations, adaptation climatique), avec participation directe des agriculteurs aux travaux parlementaires.", 'Sources : Agreste, MSA, ministère de l\'Agriculture.', 'Parlement, ministère de l\'Agriculture'],
            ['lettre-senat-6-contexte-geopolitique', 'Tous les citoyens', 'Démocratie et dialogue', 'Expliquer clairement le contexte géopolitique', "Exposer aux citoyens les conséquences économiques, sociales et humaines du contexte international (guerre en Ukraine, énergie, menaces cyber, sécurité européenne). La sécurité nationale ne doit pas empêcher le débat démocratique.", 'Exposé factuel à sourcer (ministères, institutions européennes) ; aucune analyse partisane.', 'Gouvernement, Parlement'],
            ['lettre-senat-7-paix-civile', 'Tous les citoyens', 'Démocratie et dialogue', 'Préserver la paix civile', "Refus de l'escalade de la violence ; contestation exercée dans le respect du droit ; priorité au dialogue, à la médiation et aux moyens démocratiques de résolution des conflits, pour qu'une crise sociale ne devienne pas une crise sécuritaire.", 'Principe commun à toutes les parties : aucune accusation nominative sans source publique.', 'Pouvoirs publics, organisations représentatives, citoyens'],
            ['lettre-senat-8-parole-aux-citoyens', 'Tous les citoyens', 'Démocratie et dialogue', 'Donner la parole aux citoyens', "Débat public au Sénat, mission d'information ou de contrôle avec état des lieux documenté, auditions des secteurs concernés, publication des conclusions et propositions, traduction en proposition de loi lorsque cela relève du Parlement, et examen d'un référendum d'initiative partagée (article 11 de la Constitution) lorsque les conditions sont réunies.", 'Vérifier les conditions constitutionnelles (article 11) sur Légifrance avant toute mention d\'un référendum.', 'Sénat, Parlement'],
        ];
        $created = 0;
        foreach ($lettre as [$slug, $group, $theme, $title, $summary, $verif, $authority]) {
            if ($repo->findOneBy(['slug' => $slug])) {
                continue;
            }
            $this->em->persist((new Revendication())->setSlug($slug)->setTitle($title)->setGroupLabel($group)->setTheme($theme)->setSummary($summary)
                ->setVerificationNote($verif)->setCompetentAuthority($authority)->setSourceLabel('Lettre ouverte au Sénat : pour un débat national et une consultation citoyenne (texte fourni au projet)')->setIsPublished(false));
            ++$created;
        }
        foreach ($items as [$slug, $group, $theme, $summary, $verif, $authority]) {
            if ($repo->findOneBy(['slug' => $slug])) {
                continue;
            }
            $r = (new Revendication())->setSlug($slug)->setTitle(ucfirst(str_replace('-', ' ', $slug)))->setGroupLabel($group)
                ->setTheme($theme)->setSummary($summary)->setVerificationNote($verif)->setCompetentAuthority($authority)
                ->setSourceLabel('Documents fournis au projet (octobre 2026)')->setIsPublished(false);
            $this->em->persist($r);
            ++$created;
        }
        foreach ($plan as [$slug, $title, $summary, $garde, $authority]) {
            if ($repo->findOneBy(['slug' => $slug])) {
                continue;
            }
            $r = (new Revendication())->setSlug($slug)->setTitle($title)->setGroupLabel('Tous les citoyens')
                ->setTheme('Finances publiques')->setSummary($summary)
                ->setVerificationNote('Proposition à instruire, pas une économie acquise. Garde-fou : ' . $garde . ' Aucun chiffrage certifié : coût et gains à établir par une équipe indépendante.')
                ->setCompetentAuthority($authority)->setSourceLabel('Plan Marshall citoyen, programme de travail, octobre 2026')->setIsPublished(true);
            $this->em->persist($r);
            ++$created;
        }
        $this->em->flush();
        $output->writeln(sprintf('%d revendication(s) créée(s), dont celles du Plan Marshall publiées (propositions à instruire) et les autres NON publiées. Relisez-les dans l\'administration puis cochez « Publiée ».', $created));

        return Command::SUCCESS;
    }
}
