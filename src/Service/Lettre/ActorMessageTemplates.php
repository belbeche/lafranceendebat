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

    public static function apply(string $body, ActorContact $a): string
    {
        $accroche = self::ACCROCHES[$a->getCategory()] ?? self::ACCROCHES['autre'];

        return strtr($body, [
            '{organisation}' => $a->getOrganisation(),
            '{type}' => $a->getCategoryLabel(),
            '{ville}' => (string) ($a->getCity() ?: ''),
            '{accroche}' => $accroche,
        ]);
    }
}
