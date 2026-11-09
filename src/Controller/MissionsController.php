<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Missions ouvertes à chacun : bénévoles ou rémunérées via les services (prix fixé par le prestataire, commission affichée avant paiement). */
class MissionsController extends AbstractController
{
    /** [titre, besoin, qui, paie (paid|free|both), route d'action, libellé] */
    private const MISSIONS = [
        ['Relire et apaiser les échanges', "Garder le débat respectueux : relire les contributions en attente, signaler les dérapages.", 'Savoir écouter', 'free', 'app_role_request', 'Devenir médiateur'],
        ['Vérifier les sources', "Contrôler qu'une revendication ou une proposition s'appuie sur une source publique fiable.", 'Aucune compétence particulière', 'free', 'app_revendication_new', 'Proposer une revendication sourcée'],
        ['Traduire pour que le monde participe', "Relire ou corriger la traduction d'une proposition, d'un débat ou d'une revendication.", 'Aucune compétence particulière', 'free', 'app_revendications', 'Choisir un contenu à traduire'],
        ['Rédiger des fiches pour les élus', "Transformer une proposition soutenue en dossier clair, chiffré, prêt à transmettre.", 'Compétence professionnelle utile', 'paid', 'app_role_request', 'Proposer ce service'],
        ['Accompagner une association', "Formation, montage de projet, recherche de financements, communication.", 'Compétence professionnelle utile', 'paid', 'app_role_request', 'Proposer ce service'],
        ['Animer un débat local', "Réunir des habitants, cadrer la parole, restituer les propositions sur la plateforme.", 'Goût du contact', 'both', 'app_debats', 'Voir les débats'],
        ['Médiation entre parties', "Intervenir entre voisins, collectifs ou institutions pour sortir d'un conflit.", 'Expérience de médiation', 'paid', 'app_role_request', 'Proposer ce service'],
        ["Donner un avis motivé d'acteur", "Approuver, réserver ou refuser une proposition en expliquant pourquoi.", 'Aucune compétence particulière', 'free', 'app_propositions', 'Voir les propositions'],
        ['Numérique, design, données', "Améliorer la plateforme, produire des visualisations des propositions et des chiffres publics.", 'Numérique, design ou données', 'paid', 'app_role_request', 'Proposer ce service'],
        ['Faire connaître et inviter', "Partager la plateforme, inviter des acteurs, organiser une rencontre.", 'Aucune compétence particulière', 'free', 'app_welcome', 'Me guider'],
        ['Vérifier une information', "Contrôler une rumeur ou un chiffre avec des sources publiques, puis publier la vérification dans un débat ou une revendication.", 'Aucune compétence particulière', 'free', 'app_dossiers', 'Lire la méthode'],
        ['Enquêter et publier une tribune sourcée', "Documenter un sujet d'intérêt public, signer un texte avec ses sources, le soumettre à relecture.", 'Écriture et rigueur', 'both', 'app_tribunes', 'Espace journalistes'],
        ["Atelier d'éducation aux médias et à l'histoire", "Animer une séance pour apprendre à reconnaître la propagande et à vérifier une source, à partir de l'histoire du XXe siècle.", 'Pédagogie', 'paid', 'app_dossiers', 'Voir le dossier histoire'],
        ['Transmettre un savoir', "Expliquer une compétence ou un métier à d'autres (cours, tutorat, atelier) pour que le savoir circule.", 'Aucune compétence particulière', 'free', 'app_dossiers', 'Voir les dossiers'],
    ];

    /** Missions issues des mesures du Plan Marshall citoyen : [mesure, mission concrète, qui, livrable et preuve, paie] */
    private const PLAN = [
        ['Diagnostic de coûts', "Analyser les contrats, dépenses et temps administratif de 3 à 5 structures volontaires, sur 6 à 8 semaines.", 'Analyste, gestionnaire, comptable', "Tableau de départ daté et leviers classés par impact, coût, délai, risque", 'paid'],
        ['Achats groupés', "Coordonner un achat commun volontaire (téléphonie, matériel reconditionné) entre structures voisines.", 'Acheteur, coordinateur local', "Comparatif de prix et contrat signé", 'paid'],
        ['Mutualisation des fonctions support', "Mettre en place comptabilité, paie ou secrétariat partagés, avec possibilité de sortie.", 'Comptable, RH, indépendant', "Convention de mutualisation et coût avant et après", 'paid'],
        ['Revue des subventions et de la charge administrative', "Mesurer le temps passé en reporting et proposer des simplifications, sans déplacer une dépense d'un financeur à l'autre.", 'Juriste, gestionnaire de projet', "Note de simplification vérifiée juridiquement", 'paid'],
        ['Inventaire des actifs sous-utilisés', "Recenser locaux, véhicules et équipements inutilisés, puis vérifier juridiquement leur réaffectation.", 'Bénévole local, juriste', "Inventaire publié sans données personnelles", 'both'],
        ['Évaluation externe indépendante', "Calculer les économies nettes, présenter les limites et rédiger un rapport indépendant. Aucune rémunération indexée sur des économies non vérifiées.", 'Évaluateur indépendant', "Rapport d'évaluation, résultats négatifs inclus", 'paid'],
        ['Pilote de 100 jours en Normandie', "Porter le pilote : lettres de mission, comité de pilotage, registre des décisions et des conflits d'intérêts.", 'Porteur de projet, élu référent', "Lettres de mission signées et registre public", 'both'],
        ['Chiffrage des mesures nationales', "Chiffrer une des douze mesures à instruire avec méthode et garde-fou, avant toute généralisation.", 'Économiste, expert public', "Note de chiffrage avec hypothèses", 'both'],
    ];

    /** Mission (index dans MISSIONS) => rôles des acteurs concernés qui reçoivent les candidatures. */
    public const TARGETS = [12 => ['ROLE_ASSOCIATION', 'ROLE_ELU'], 3 => ['ROLE_ELU'], 4 => ['ROLE_ASSOCIATION'], 5 => ['ROLE_ASSOCIATION', 'ROLE_ELU'], 6 => ['ROLE_ASSOCIATION', 'ROLE_ELU'], 8 => ['ROLE_ASSOCIATION', 'ROLE_ACTOR'], 9 => ['ROLE_ASSOCIATION', 'ROLE_ELU']] + self::OTHER_TARGETS;
    /** Missions ouvertes à tous les types d'acteurs concernés. */
    public const OTHER_TARGETS = [0 => ['ROLE_MODERATOR', 'ROLE_ASSOCIATION'], 1 => ['ROLE_JOURNALIST', 'ROLE_MODERATOR', 'ROLE_ASSOCIATION'], 2 => ['ROLE_ASSOCIATION', 'ROLE_ACTOR'], 7 => ['ROLE_ELU', 'ROLE_ASSOCIATION', 'ROLE_ACTOR'], 10 => ['ROLE_JOURNALIST', 'ROLE_ASSOCIATION', 'ROLE_ACTOR'], 11 => ['ROLE_JOURNALIST', 'ROLE_ASSOCIATION', 'ROLE_ACTOR'], 13 => ['ROLE_ASSOCIATION', 'ROLE_ACTOR', 'ROLE_ELU']];
    public const PLAN_TARGETS = ['ROLE_ELU', 'ROLE_ASSOCIATION', 'ROLE_ACTOR'];

    /** Mots-cles de competence par mission (m = missions de la plateforme, p = Plan Marshall). Vide = besoin general. */
    public const KEYWORDS = [
        'm0' => ['mediat', 'apais', 'dialogue', 'conflit'], 'm1' => ['verif', 'fact', 'source', 'journal', 'donnee', 'recherche'], 'm2' => ['traduc', 'langue', 'interpret', 'linguis'],
        'm3' => ['redact', 'politique', 'collectivit', 'conseil', 'analyse', 'rapport'], 'm4' => ['accompagn', 'formation', 'projet', 'financ', 'subvention', 'communication', 'conseil'],
        'm5' => ['animat', 'debat', 'reunion', 'concertation', 'habitant'], 'm6' => ['mediat', 'conflit', 'conciliat', 'negoci'], 'm7' => [],
        'm8' => ['numerique', 'design', 'donnee', 'web', 'developp', 'data', 'logiciel', 'graphi'], 'm9' => [], 'm10' => ['verif', 'fact', 'journal', 'donnee', 'recherche', 'statist'],
        'm11' => ['journal', 'redact', 'ecri', 'press', 'enquet'], 'm12' => ['education', 'formation', 'pedagog', 'histoire', 'media', 'enseign', 'ecole'], 'm13' => ['formation', 'enseign', 'pedagog', 'cours', 'atelier', 'mentor', 'metier'],
        'p0' => ['comptab', 'gestion', 'audit', 'financ', 'analyse', 'cout', 'conseil'], 'p1' => ['achat', 'negoci', 'fournisseur', 'logistique', 'cooperat', 'mutualis'],
        'p2' => ['comptab', 'paie', 'ressources humaines', 'secretariat', 'gestion', 'mutualis'], 'p3' => ['subvention', 'juridi', 'droit', 'administr', 'financ'],
        'p4' => ['immobil', 'patrimoine', 'inventaire', 'locaux', 'juridi', 'foncier'], 'p5' => ['evaluat', 'audit', 'etude', 'statist', 'economi', 'recherche'],
        'p6' => ['normand', 'projet', 'pilot', 'coordination', 'territoire'], 'p7' => ['economi', 'chiffr', 'statist', 'budget', 'financ', 'analyse'],
    ];

    /** Profil d'inscription propose quand personne ne correspond : role => [parametre ?profil=, libelle]. */
    public const SIGNUP = ['ROLE_ASSOCIATION' => ['association', 'une association'], 'ROLE_ELU' => ['elu', 'un élu ou une institution'], 'ROLE_ACTOR' => ['expert', 'un prestataire ou un expert']];

    /** Missions rémunérées ou mixtes mises en avant : [clé, titre, besoin, qui, paie]. */
    public static function paidHighlights(int $limit = 4): array
    {
        $out = [];
        foreach (self::PLAN as $i => $m) {
            if (count($out) >= $limit) {
                break;
            }
            $out[] = ['p' . $i, $m[0], $m[1], $m[2], $m[4]];
        }

        return $out;
    }

    public static function missionByKey(string $key): ?array
    {
        if (preg_match('/^m(\d+)$/', $key, $m) && isset(self::TARGETS[(int) $m[1]])) {
            return [self::MISSIONS[(int) $m[1]][0], self::MISSIONS[(int) $m[1]][1], self::TARGETS[(int) $m[1]]];
        }
        if (preg_match('/^p(\d+)$/', $key, $m) && isset(self::PLAN[(int) $m[1]])) {
            return ['Plan Marshall citoyen : ' . self::PLAN[(int) $m[1]][0], self::PLAN[(int) $m[1]][1], self::PLAN_TARGETS];
        }

        return null;
    }

    #[Route('/missions', name: 'app_missions', methods: ['GET'])]
    public function index(\App\Service\ActorDirectory $directory): Response
    {
        $missionActors = [];
        foreach (self::TARGETS as $i => $roles) {
            $missionActors['m' . $i] = $directory->match($roles, self::KEYWORDS['m' . $i] ?? [], 3) + ['roles' => $roles];
        }
        $planActors = [];
        foreach (array_keys(self::PLAN) as $i) {
            $planActors['p' . $i] = $directory->match(self::PLAN_TARGETS, self::KEYWORDS['p' . $i] ?? [], 3) + ['roles' => self::PLAN_TARGETS];
        }

        return $this->render('missions/index.html.twig', ['missions' => self::MISSIONS, 'plan' => self::PLAN, 'targets' => self::TARGETS, 'missionActors' => $missionActors, 'planActors' => $planActors, 'signup' => self::SIGNUP]);
    }

    #[Route('/tutoriels', name: 'app_tutorials', methods: ['GET'])]
    public function tutorials(): Response
    {
        return $this->render('missions/tutorials.html.twig');
    }
}
