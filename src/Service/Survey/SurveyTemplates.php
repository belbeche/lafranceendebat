<?php

namespace App\Service\Survey;

use App\Entity\Proposition;

/**
 * Modèles d'enquête prêts à l'emploi. Chaque modèle renvoie le titre, la présentation et les questions
 * au format de Survey::parse() ; l'administrateur relit et ajuste avant l'aperçu obligatoire.
 * Les sujets proposés sont importés de la liste officielle des thèmes de la plateforme (Proposition::THEMES).
 */
final class SurveyTemplates
{
    public const LABELS = [
        'priorites' => 'Priorités des habitants (sujets importés de la plateforme)',
        'services' => 'Accès aux services publics du quotidien',
        'terrain' => 'Besoins d\'un territoire (enquête de terrain)',
        'dialogue' => 'Qualité du dialogue et confiance',
    ];

    /** @return array{title:string,intro:string,questions:string}|null */
    public static function get(string $key): ?array
    {
        $themes = implode(' ; ', Proposition::THEMES);

        return match ($key) {
            'priorites' => [
                'title' => 'Quelles sont vos priorités pour les prochaines années ?',
                'intro' => 'Cette enquête anonyme recense les sujets qui comptent le plus pour vous. Elle prend trois minutes, sans compte. Les résultats agrégés seront publiés avec le nombre de réponses et la méthode.',
                'questions' => "choix* | Quel sujet est votre première priorité ? | $themes\nmultiple | Quels autres sujets méritent un débat public ? | $themes\nechelle | Vous sentez-vous entendu(e) sur ces sujets aujourd'hui ?\ntexte | Une proposition concrète à nous confier ?",
            ],
            'services' => [
                'title' => 'Accès aux services publics près de chez vous',
                'intro' => 'Nous recueillons des constats précis sur l\'accès aux services du quotidien (santé, école, transports, démarches). Vos réponses sont anonymes et serviront à documenter des besoins avec des chiffres.',
                'questions' => "choix* | Quel service est le plus difficile d'accès pour vous ? | Santé ; École ; Transports ; Démarches administratives ; Justice ; Sécurité ; Aucun\nechelle* | Quelle est la qualité de ce service près de chez vous ?\nchoix | Quel est le principal obstacle ? | Distance ; Délai d'attente ; Coût ; Complexité ; Manque d'information\noui-non | Avez-vous renoncé à une démarche ou à des soins ces douze derniers mois ?\ntexte | Décrivez la situation en quelques mots, sans nommer de personne.",
            ],
            'terrain' => [
                'title' => 'Enquête de terrain : besoins du territoire',
                'intro' => 'Questionnaire à remplir sur place (papier ou entretien) puis saisi par un modérateur, ou en ligne. Chaque réponse est comptée une seule fois ; le lieu de collecte est consigné pour la traçabilité.',
                'questions' => "choix* | Quel est le problème numéro un de votre territoire ? | $themes\nechelle* | Êtes-vous satisfait(e) de la vie dans votre commune ?\nchoix | Depuis combien de temps y habitez-vous ? | Moins de 2 ans ; 2 à 10 ans ; Plus de 10 ans\nchoix | Votre tranche d'âge | Moins de 25 ans ; 25 à 44 ans ; 45 à 64 ans ; 65 ans et plus\ntexte | Quelle solution concrète proposez-vous ?",
            ],
            'dialogue' => [
                'title' => 'Dialogue et confiance entre citoyens',
                'intro' => 'Comment vivez-vous le débat public aujourd\'hui ? Cette enquête anonyme mesure la qualité perçue du dialogue, sans jugement ni étiquette.',
                'questions' => "echelle* | Pouvez-vous débattre calmement avec quelqu'un qui pense autrement ?\nchoix | Où le dialogue vous paraît-il le plus difficile ? | En famille ; Au travail ; Sur les réseaux sociaux ; Dans les médias ; Dans la vie locale\nmultiple | Qu'est-ce qui améliorerait le dialogue ? | Sources vérifiables ; Modération claire ; Règles de respect ; Médiation ; Débats locaux\ntexte | Une idée pour apaiser les échanges ?",
            ],
            default => null,
        };
    }
}
