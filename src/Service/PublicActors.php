<?php

namespace App\Service;

/**
 * Annuaire des voies concretes pour porter une proposition aupres des institutions.
 * Aucun faux partenariat : ce sont des liens vers des services officiels (ou citoyens independants, signales comme tels).
 * Les seuils et conditions evoluent : verifiez-les toujours sur le site officiel avant de communiquer.
 */
final class PublicActors
{
    public const CATEGORIES = [
        'petition' => 'Pétitions officielles',
        'referendum' => 'Référendum',
        'elus' => 'Écrire à vos élus',
        'executif' => 'Gouvernement et Présidence',
        'recours' => 'Recours et médiation',
        'local' => 'Votre commune et votre territoire',
    ];

    /** @return list<array{slug:string,name:string,category:string,summary:string,how:string,url:string,official:bool,salutation:string}> */
    public static function all(): array
    {
        return [
            ['slug' => 'senat-petitions', 'name' => 'Sénat : pétitions', 'category' => 'petition', 'official' => true, 'salutation' => 'Madame la Présidente, Monsieur le Président du Sénat,',
                'summary' => 'Une pétition déposée sur le site du Sénat peut être examinée si elle réunit assez de signatures.',
                'how' => 'Le seuil indiqué par le Sénat est de 100 000 signatures sur 6 mois, puis la Conférence des présidents décide des suites. Vérifiez les conditions sur le site.',
                'url' => 'https://petitions.senat.fr/'],
            ['slug' => 'an-petitions', 'name' => 'Assemblée nationale : pétitions', 'category' => 'petition', 'official' => true, 'salutation' => 'Madame la Présidente, Monsieur le Président de l\'Assemblée nationale,',
                'summary' => 'Les pétitions de l\'Assemblée nationale peuvent être examinées par la commission compétente.',
                'how' => 'Le seuil indiqué est de 500 000 signataires issus d\'au moins 30 départements ou collectivités. Vérifiez les conditions sur le site.',
                'url' => 'https://petitions.assemblee-nationale.fr/'],
            ['slug' => 'rip', 'name' => 'Référendum d\'initiative partagée', 'category' => 'referendum', 'official' => true, 'salutation' => 'Mesdames, Messieurs les parlementaires,',
                'summary' => 'Procédure qui permet à des parlementaires et à des électeurs de demander un référendum sur certains sujets.',
                'how' => 'Elle exige le soutien de 185 parlementaires puis d\'environ un dixième du corps électoral. Le champ est limité par la Constitution.',
                'url' => 'https://www.referendum.interieur.gouv.fr/'],
            ['slug' => 'depute', 'name' => 'Votre député', 'category' => 'elus', 'official' => false, 'salutation' => 'Madame la Députée, Monsieur le Député,',
                'summary' => 'Un député peut relayer une proposition, poser une question écrite ou déposer un amendement.',
                'how' => 'Trouvez votre député par votre commune, puis écrivez-lui ou demandez un rendez-vous en circonscription. NosDéputés.fr est un observatoire citoyen indépendant, non officiel.',
                'url' => 'https://www.nosdeputes.fr/'],
            ['slug' => 'senateur', 'name' => 'Votre sénateur', 'category' => 'elus', 'official' => false, 'salutation' => 'Madame la Sénatrice, Monsieur le Sénateur,',
                'summary' => 'Un sénateur représente les collectivités de votre département et peut relayer votre proposition.',
                'how' => 'Trouvez vos sénateurs par département, puis écrivez-leur. NosSénateurs.fr est un observatoire citoyen indépendant, non officiel.',
                'url' => 'https://www.nossenateurs.fr/'],
            ['slug' => 'presidence', 'name' => 'Présidence de la République', 'category' => 'executif', 'official' => true, 'salutation' => 'Monsieur le Président de la République,',
                'summary' => 'Vous pouvez écrire à la Présidence ; la réponse n\'est pas garantie.',
                'how' => 'Utilisez le formulaire de contact officiel du site de l\'Élysée.',
                'url' => 'https://www.elysee.fr/'],
            ['slug' => 'gouvernement', 'name' => 'Gouvernement et ministères', 'category' => 'executif', 'official' => true, 'salutation' => 'Madame la Ministre, Monsieur le Ministre,',
                'summary' => 'Le ministère compétent peut étudier une mesure relevant de son champ.',
                'how' => 'Identifiez le ministère concerné et utilisez son formulaire de contact. Servez-vous de la rubrique « Qui peut décider ? » de la fiche.',
                'url' => 'https://www.gouvernement.fr/'],
            ['slug' => 'defenseur', 'name' => 'Défenseur des droits', 'category' => 'recours', 'official' => true, 'salutation' => 'Madame la Défenseure des droits, Monsieur le Défenseur des droits,',
                'summary' => 'Autorité indépendante : droits des usagers des services publics, discriminations, droits de l\'enfant, déontologie de la sécurité.',
                'how' => 'La saisine est gratuite. Elle concerne une situation précise et non une proposition générale de loi.',
                'url' => 'https://www.defenseurdesdroits.fr/'],
            ['slug' => 'cese', 'name' => 'Conseil économique, social et environnemental', 'category' => 'petition', 'official' => true, 'salutation' => 'Madame la Présidente, Monsieur le Président du CESE,',
                'summary' => 'Le CESE reçoit des pétitions citoyennes et rend des avis.',
                'how' => 'Consultez sur son site les conditions de recevabilité en vigueur.',
                'url' => 'https://www.lecese.fr/'],
            ['slug' => 'mairie', 'name' => 'Votre mairie et votre conseil municipal', 'category' => 'local', 'official' => true, 'salutation' => 'Madame la Maire, Monsieur le Maire,',
                'summary' => 'Le niveau le plus accessible : séances du conseil ouvertes au public, rencontres avec les élus, budgets participatifs.',
                'how' => 'Retrouvez les coordonnées de votre mairie dans l\'annuaire officiel du service public.',
                'url' => 'https://lannuaire.service-public.fr/'],
            ['slug' => 'territoire', 'name' => 'Département et Région', 'category' => 'local', 'official' => true, 'salutation' => 'Madame la Présidente, Monsieur le Président,',
                'summary' => 'Les départements et régions décident de sujets comme les collèges, lycées, transports, formation et développement économique.',
                'how' => 'Retrouvez les coordonnées dans l\'annuaire officiel du service public.',
                'url' => 'https://lannuaire.service-public.fr/'],
        ];
    }

    /** @return array<string, array> */
    public static function grouped(): array
    {
        $out = [];
        foreach (self::CATEGORIES as $key => $label) {
            $rows = array_values(array_filter(self::all(), static fn (array $a): bool => $a['category'] === $key));
            if ($rows) {
                $out[$key] = ['label' => $label, 'items' => $rows];
            }
        }

        return $out;
    }

    public static function find(string $slug): ?array
    {
        foreach (self::all() as $a) {
            if ($a['slug'] === $slug) {
                return $a;
            }
        }

        return null;
    }
}
