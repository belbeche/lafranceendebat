<?php

namespace App\Service;

use App\Controller\DossierController;
use App\Entity\Dossier;
use App\Entity\Proposition;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Catalogue unique des dossiers : dossiers éditoriaux, dossiers de départ par thème, dossiers citoyens publiés.
 * Un débat ou une proposition référence un dossier par son identifiant (slug).
 */
class DossierCatalog
{
    /** Liens officiels par thème : dossiers de départ, à compléter par la communauté. */
    public const STARTERS = [
        'Finances publiques' => ['Comprendre les finances publiques', 'Budget de l\'État, dette, recettes et dépenses : les chiffres officiels pour débattre sur les mêmes bases.', ['https://www.ccomptes.fr', 'https://www.budget.gouv.fr', 'https://www.insee.fr', 'https://www.vie-publique.fr']],
        'Industrie et recherche' => ['Industrie et recherche en France', 'Production, innovation et financement de la recherche : où trouver les données publiques.', ['https://www.enseignementsup-recherche.gouv.fr', 'https://www.economie.gouv.fr', 'https://www.insee.fr']],
        'Emploi et formation' => ['Emploi et formation', 'Chômage, contrats, apprentissage et formation professionnelle : statistiques et droits.', ['https://dares.travail-emploi.gouv.fr', 'https://www.francetravail.fr', 'https://www.service-public.fr', 'https://www.insee.fr']],
        'Pouvoir d\'achat et logement' => ['Pouvoir d\'achat et logement', 'Prix, salaires, loyers et aides : les indicateurs officiels et les droits des locataires et propriétaires.', ['https://www.insee.fr', 'https://www.anil.org', 'https://www.service-public.fr']],
        'Santé' => ['Santé et accès aux soins', 'Organisation du système de santé, accès aux soins et prévention : données et avis publics.', ['https://drees.solidarites-sante.gouv.fr', 'https://sante.gouv.fr', 'https://www.has-sante.fr', 'https://www.ameli.fr']],
        'École et transmission' => ['École et transmission', 'Programmes, résultats et organisation de l\'école. Voir aussi notre dossier historique sur l\'éducation.', ['https://www.education.gouv.fr', 'https://www.enseignementsup-recherche.gouv.fr']],
        'Agriculture et alimentation' => ['Agriculture et alimentation', 'Exploitations, revenus, sécurité sanitaire et alimentation : statistiques agricoles officielles.', ['https://agriculture.gouv.fr', 'https://agreste.agriculture.gouv.fr', 'https://www.anses.fr']],
        'Justice' => ['Justice : fonctionnement et droits', 'Organisation des juridictions, délais, droits des justiciables et textes de loi.', ['https://www.justice.gouv.fr', 'https://www.legifrance.gouv.fr', 'https://www.vie-publique.fr']],
        'Sécurité et réinsertion' => ['Sécurité et réinsertion', 'Statistiques de sécurité, prisons, peines et prévention de la récidive.', ['https://www.interieur.gouv.fr', 'https://www.justice.gouv.fr', 'https://www.cglpl.fr']],
        'Services publics' => ['Services publics', 'Accès aux services publics, démarches et recours : droits et organisation.', ['https://www.service-public.fr', 'https://www.defenseurdesdroits.fr']],
        'Retraites et solidarités' => ['Retraites et solidarités', 'Régimes de retraite, minima sociaux et solidarité : rapports et données officiels.', ['https://www.cor-retraites.fr', 'https://drees.solidarites-sante.gouv.fr', 'https://www.service-public.fr']],
        'Écologie et énergie' => ['Écologie et énergie', 'Climat, énergie, biodiversité et transports : chiffres et plans publics.', ['https://www.ecologie.gouv.fr', 'https://www.ademe.fr', 'https://www.rte-france.com']],
        'Démocratie et dialogue' => ['Démocratie et dialogue', 'Institutions, droit de vote, consultations et participation citoyenne : textes de référence.', ['https://www.assemblee-nationale.fr', 'https://www.senat.fr', 'https://www.conseil-constitutionnel.fr', 'https://www.vie-publique.fr']],
        'Défense et résilience' => ['Défense et résilience', 'Défense nationale, protection des populations et gestion de crise : documents publics.', ['https://www.defense.gouv.fr', 'https://www.sgdsn.gouv.fr']],
        'Europe et international' => ['Europe et international', 'Institutions européennes, traités et diplomatie : sources officielles.', ['https://www.diplomatie.gouv.fr', 'https://european-union.europa.eu', 'https://www.touteleurope.eu']],
    ];

    public function __construct(private readonly EntityManagerInterface $em) {}

    public static function starterSlug(string $theme): string
    {
        return 'theme-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(strtr(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $theme) ?: $theme, ["'" => '', '`' => '', '^' => '', '~' => '', '"' => '']))), '-');
    }

    /** @return array<string, array{0:string,1:string,2:list<string>}> slug => [titre, intro, liens] */
    public function starters(): array
    {
        $out = [];
        foreach (self::STARTERS as $theme => $s) {
            $out[self::starterSlug($theme)] = [$s[0], $s[1], $s[2], $theme];
        }

        return $out;
    }

    /** @return list<Dossier> */
    public function published(): array
    {
        return $this->em->getRepository(Dossier::class)->findBy(['status' => Dossier::STATUS_PUBLISHED], ['title' => 'ASC']);
    }

    /** Liste unique [slug => titre] proposée dans les formulaires (dossiers publiés + dossiers en attente de l'utilisateur). */
    public function options(?User $user = null): array
    {
        $o = [];
        foreach (DossierController::DOSSIERS as $slug => $d) {
            $o[$slug] = $d[0];
        }
        foreach ($this->starters() as $slug => $s) {
            $o[$slug] = $s[0];
        }
        foreach ($this->published() as $d) {
            $o[$d->getSlug()] = $d->getTitle();
        }
        if ($user) {
            foreach ($this->em->getRepository(Dossier::class)->findBy(['author' => $user, 'status' => Dossier::STATUS_PENDING]) as $d) {
                $o[$d->getSlug()] = $d->getTitle() . ' (en validation)';
            }
        }

        return $o;
    }

    public function isValidChoice(string $slug, ?User $user = null): bool
    {
        return isset($this->options($user)[$slug]);
    }

    /** Titre et adresse d'un dossier publié, ou null s'il n'est pas (encore) public. */
    public function link(?string $slug): ?array
    {
        if (!$slug) {
            return null;
        }
        if (isset(DossierController::DOSSIERS[$slug])) {
            return ['title' => DossierController::DOSSIERS[$slug][0], 'slug' => $slug, 'builtin' => true];
        }
        if (isset($this->starters()[$slug])) {
            return ['title' => $this->starters()[$slug][0], 'slug' => $slug, 'builtin' => false];
        }
        $d = $this->em->getRepository(Dossier::class)->findOneBy(['slug' => $slug, 'status' => Dossier::STATUS_PUBLISHED]);

        return $d ? ['title' => $d->getTitle(), 'slug' => $slug, 'builtin' => false] : null;
    }
}
