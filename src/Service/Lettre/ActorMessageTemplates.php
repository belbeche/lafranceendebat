<?php

namespace App\Service\Lettre;

use App\Entity\ActorContact;

/**
 * Messages adaptés à chaque catégorie d'acteur : le texte écrit par l'administrateur peut contenir
 * {organisation}, {type}, {ville} et {accroche}, remplacés pour chaque destinataire.
 */
final class ActorMessageTemplates
{
    public const ACCROCHES = [
        'commune' => "Les habitants de votre commune s'expriment déjà sur La France en Débat. Vous pouvez répondre publiquement à leurs propositions, avec vos sources, et montrer ce que votre collectivité fait ou peut faire.",
        'departement' => "Les propositions des habitants de votre département touchent à vos compétences : solidarités, collèges, routes, protection de l'enfance. Vous pouvez y répondre publiquement et expliquer vos choix.",
        'region' => "Transports, formation, développement économique : les propositions des citoyens concernent souvent vos compétences. Vous pouvez y répondre publiquement et signaler ce qui est déjà en cours.",
        'parlementaire' => "Des citoyens y débattent de textes et de sujets qui relèvent de votre mandat. Vous pouvez leur répondre, préciser votre position et suivre les propositions qui reçoivent le plus de soutiens.",
        'administration' => "Des citoyens y posent des questions sur des services et des règles qui relèvent de votre administration. Vous pouvez apporter une réponse officielle et sourcée, visible de tous.",
        'association' => "Les associations sont au cœur des solutions locales. Vous pouvez y présenter vos actions, répondre aux besoins exprimés par les habitants et trouver des partenaires.",
        'autre' => "La plateforme réunit citoyens, associations et acteurs publics pour débattre sans haine et suivre les engagements. Vous pouvez y répondre aux propositions qui vous concernent.",
    ];

    /** Objet d'invitation propre à chaque catégorie. */
    public const OBJETS = [
        'commune' => 'Les habitants de votre commune débattent : voulez-vous leur répondre ?',
        'departement' => 'Propositions de citoyens sur les compétences du département',
        'region' => 'Propositions de citoyens sur vos compétences régionales',
        'parlementaire' => 'Des citoyens débattent de sujets de votre mandat',
        'administration' => 'Questions de citoyens sur vos services : une réponse officielle est possible',
        'association' => 'Présentez vos actions et trouvez des partenaires',
        'autre' => 'Invitation à contribuer à une initiative citoyenne de dialogue',
    ];

    /**
     * Modèle de lettre complet pour une catégorie : l'administrateur le charge, relit, ajuste, puis envoie.
     * Le texte reste neutre : aucune position, aucune promesse, deux façons de participer (compte ou anonyme).
     *
     * @return array{subject:string,body:string}
     */
    public static function modele(string $category): array
    {
        $category = isset(self::ACCROCHES[$category]) ? $category : 'autre';

        return [
            'subject' => self::OBJETS[$category],
            'body' => "Bonjour,\n\nVous recevez ce message à propos de votre fonction ({type}, {organisation}).\n\n{accroche}\n\n"
                . "La France en Débat est une plateforme citoyenne indépendante : on y débat sans insulte, chaque affirmation peut être sourcée, et la modération est publique.\n\n"
                . "Deux façons de participer :\n"
                . "1. Avec un compte, pour répondre publiquement et suivre les échanges.\n"
                . "2. Sans compte et de façon anonyme : {participation_anonyme}\n"
                . "Nous ne conservons ni adresse IP ni e-mail avec une contribution anonyme, et rien n'est publié sans relecture.\n\n"
                . "Si ce message ne vous concerne pas, le lien de désinscription en bas supprime votre adresse immédiatement.\n\nCordialement,\nL'équipe de La France en Débat",
        ];
    }

    public static function apply(string $body, ActorContact $a, string $anonymousUrl = ''): string
    {
        $accroche = self::ACCROCHES[$a->getCategory()] ?? self::ACCROCHES['autre'];

        return strtr($body, [
            '{organisation}' => $a->getOrganisation(),
            '{type}' => $a->getCategoryLabel(),
            '{ville}' => (string) ($a->getCity() ?: ''),
            '{accroche}' => $accroche,
            '{participation_anonyme}' => $anonymousUrl,
        ]);
    }
}
