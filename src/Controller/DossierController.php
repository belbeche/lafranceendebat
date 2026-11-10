<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Dossiers éditoriaux sourcés : pétition citoyenne, éducation, Constitution, appel à la paix. */
class DossierController extends AbstractController
{
    public const DOSSIERS = [
        'petition' => ['Pétition : un débat national sur les priorités de la France', 'Initiative n° 7234 sur le site de l\'Assemblée nationale, en cours de collecte.'],
        'education' => ['Éducation : le savoir se partage, il ne se perd pas', 'Comment la France a organisé l\'école, et pourquoi la transmission des connaissances protège la paix civile.'],
        'constitution' => ['La Constitution et notre démarche', 'Ce que le texte permet aux citoyens, ses limites, et comment notre idée s\'y inscrit.'],
        'histoire-guerres' => ['Collaborer plutôt qu\'opposer : ce que l\'histoire des guerres nous apprend', 'De 1914 à 1950, comment la propagande et la division ont mené à la guerre, et comment la coopération a construit la paix.'],
        'terrorisme-memoire' => ['Terrorisme et haine en France : se souvenir de toutes les victimes', 'Ce que dit la loi (article 421-1 du Code pénal) et les faits qui ont marqué la France depuis 1982.'],
        'ils-ont-change-le-monde' => ['Ils et elles ont changé le monde', 'Des parcours de chercheurs, de militantes et de citoyens dont l\'action a fait avancer la science, le droit et la dignité.'],
        'ia-controle-humain' => ['IA : garder le contrôle humain', 'AI Act (article 14), qualité des données et limites du code généré : ce qu\'il faut savoir avant de déléguer à une machine.'],
        'appel-paix' => ['Appel à la jeunesse : colère oui, escalade non', 'Un appel à l\'apaisement, avec le cadre légal qui explique pourquoi l\'escalade est dangereuse pour tous.'],
    ];

    #[Route('/dossiers', name: 'app_dossiers', methods: ['GET'])]
    public function index(\App\Service\DossierCatalog $catalog): Response
    {
        // Un dossier publié en base avec le même adresse qu'un dossier intégré le remplace (titre et résumé modifiables sans code).
        $dossiers = self::DOSSIERS;
        $citizen = [];
        foreach ($catalog->published() as $d) {
            if (isset($dossiers[$d->getSlug()])) {
                $dossiers[$d->getSlug()] = [$d->getTitle(), $d->getIntro()];
            } elseif (!isset($catalog->starters()[$d->getSlug()])) {
                $citizen[] = $d;
            }
        }

        return $this->render('dossier/index.html.twig', ['dossiers' => $dossiers, 'starters' => $catalog->starters(), 'citizen' => $citizen]);
    }

    #[Route('/dossiers/proposer', name: 'app_dossier_new', methods: ['GET', 'POST'], priority: 10)]
    #[\Symfony\Component\Security\Http\Attribute\IsGranted('ROLE_USER')]
    public function propose(\Symfony\Component\HttpFoundation\Request $request, \Doctrine\ORM\EntityManagerInterface $em, \App\Service\ContentModerator $moderator, \Symfony\Component\String\Slugger\SluggerInterface $slugger): Response
    {
        $d = ['title' => '', 'theme' => '', 'intro' => '', 'body' => '', 'sources' => ''];
        $errors = [];
        $user = $this->getUser();
        if ($request->isMethod('POST')) {
            foreach ($d as $k => $_) {
                $d[$k] = trim((string) $request->request->get($k, ''));
            }
            if (!$this->isCsrfTokenValid('dossier_new', (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée, réessayez.';
            }
            if (mb_strlen($d['title']) < 8 || mb_strlen($d['title']) > 160) {
                $errors[] = 'Le titre doit faire entre 8 et 160 caractères.';
            }
            if ($d['theme'] !== '' && !in_array($d['theme'], \App\Entity\Proposition::THEMES, true)) {
                $errors[] = 'Thème inconnu.';
            }
            if (mb_strlen($d['intro']) < 30 || mb_strlen($d['intro']) > 400) {
                $errors[] = 'Le résumé doit faire entre 30 et 400 caractères.';
            }
            if (mb_strlen($d['body']) < 200 || mb_strlen($d['body']) > 20000) {
                $errors[] = 'Le texte doit faire entre 200 et 20000 caractères, factuel et sans parti pris.';
            }
            $links = array_filter(array_map('trim', preg_split('/\R/', $d['sources']) ?: []), static fn ($l) => preg_match('#^https://\S+\.\S+$#i', $l) === 1);
            if (count($links) < 2) {
                $errors[] = 'Indiquez au moins deux sources publiques (un lien https par ligne).';
            }
            if ($moderator->analyze($d['title'] . "\n" . $d['intro'] . "\n" . $d['body']) !== []) {
                $errors[] = 'Le texte contient une insulte, une menace ou une donnée personnelle. Reformulez calmement.';
            }
            $pending = $em->getRepository(\App\Entity\Dossier::class)->count(['author' => $user, 'status' => \App\Entity\Dossier::STATUS_PENDING]);
            if (!$errors && $pending >= 3) {
                $errors[] = 'Vous avez déjà 3 dossiers en attente de validation. Patientez avant d\'en proposer un autre.';
            }
            if (!$errors) {
                $base = mb_substr(strtolower((string) $slugger->slug($d['title'])), 0, 150) ?: 'dossier';
                $slug = $base;
                $n = 1;
                while ($em->getRepository(\App\Entity\Dossier::class)->findOneBy(['slug' => $slug]) || isset(self::DOSSIERS[$slug]) || str_starts_with($slug, 'theme-') || $slug === 'proposer') {
                    $slug = $base . '-' . (++$n);
                }
                $dossier = (new \App\Entity\Dossier())->setAuthor($user)->setTitle($d['title'])->setSlug($slug)->setTheme($d['theme'] ?: null)->setIntro($d['intro'])->setBody($d['body'])->setSources(implode("\n", $links));
                $em->persist($dossier);
                $em->flush();
                $this->addFlash('success', 'Merci. Votre dossier est envoyé à la modération. Vous pouvez déjà le choisir pour un débat ou une proposition : il sera visible de tous une fois validé.');

                return $this->redirectToRoute('app_dossiers');
            }
        }

        return $this->render('dossier/new.html.twig', ['d' => $d, 'errors' => $errors, 'themes' => \App\Entity\Proposition::THEMES]);
    }

    #[Route('/dossiers/{slug}', name: 'app_dossier', methods: ['GET'], requirements: ['slug' => 'petition|education|constitution|appel-paix|histoire-guerres|terrorisme-memoire|ils-ont-change-le-monde|ia-controle-humain'])]
    public function show(string $slug, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        // Version modifiable depuis l'administration : si elle existe et est publiée, elle remplace le gabarit d'origine.
        $db = $em->getRepository(\App\Entity\Dossier::class)->findOneBy(['slug' => $slug]);
        if ($db && ($db->isPublished() || $this->isGranted('ROLE_MODERATOR'))) {
            return $this->render('dossier/citizen.html.twig', ['dossier' => $db]);
        }

        return $this->render('dossier/' . $slug . '.html.twig', ['title' => self::DOSSIERS[$slug][0], 'intro' => self::DOSSIERS[$slug][1]]);
    }

    #[Route('/dossiers/{slug}', name: 'app_dossier_page', methods: ['GET'], requirements: ['slug' => '[a-z0-9-]+'], priority: -5)]
    public function page(string $slug, \App\Service\DossierCatalog $catalog, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $override = $em->getRepository(\App\Entity\Dossier::class)->findOneBy(['slug' => $slug, 'status' => \App\Entity\Dossier::STATUS_PUBLISHED]);
        if (!$override && isset($catalog->starters()[$slug])) {
            [$title, $intro, $links, $theme] = $catalog->starters()[$slug];

            return $this->render('dossier/starter.html.twig', ['title' => $title, 'intro' => $intro, 'links' => $links, 'theme' => $theme, 'slug' => $slug]);
        }
        $dossier = $em->getRepository(\App\Entity\Dossier::class)->findOneBy(['slug' => $slug]);
        $mine = $dossier && $this->getUser() && ($dossier->getAuthor() === $this->getUser() || $this->isGranted('ROLE_MODERATOR'));
        if (!$dossier || (!$dossier->isPublished() && !$mine)) {
            throw $this->createNotFoundException();
        }

        return $this->render('dossier/citizen.html.twig', ['dossier' => $dossier]);
    }
}
