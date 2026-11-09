<?php

namespace App\Controller;

use App\Entity\Proposition;
use App\Repository\CandidatRepository;
use App\Repository\EngagementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP "CandidatController".
 *
 * Routes :
 *  - GET /candidats (app_candidats)
 *  - GET /candidats/comparer (app_candidats_compare)
 *  - GET /candidats/{slug} (app_candidat_show)
 */
#[Route('/candidats')]
class CandidatController extends AbstractController
{
    #[Route('', name: 'app_candidats', methods: ['GET'])]
    public function index(CandidatRepository $candidats, EngagementRepository $engagements): Response
    {
        // Ordre aleatoire a chaque affichage : aucun candidat n'est favorise par sa position.
        $list = $candidats->findPublished();
        shuffle($list);

        return $this->render('candidat/index.html.twig', [
            'candidats' => $list,
            'comparableThemes' => $engagements->findUsedThemes(),
        ]);
    }

    /** Comparaison d'un theme : memes criteres pour tous, ordre aleatoire a chaque affichage, aucune note. */
    #[Route('/comparer', name: 'app_candidats_compare', methods: ['GET'])]
    public function compare(Request $request, EngagementRepository $engagements): Response
    {
        $theme = (string) $request->query->get('theme', '');
        $theme = in_array($theme, Proposition::THEMES, true) ? $theme : null;
        $items = $theme ? $engagements->findByTheme($theme) : [];
        shuffle($items);

        return $this->render('candidat/compare.html.twig', [
            'theme' => $theme,
            'themes' => $engagements->findUsedThemes(),
            'engagements' => $items,
        ]);
    }

    #[Route('/{slug}', name: 'app_candidat_show', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(string $slug, CandidatRepository $candidats): Response
    {
        $candidat = $candidats->findPublishedBySlug($slug) ?? throw $this->createNotFoundException();

        return $this->render('candidat/show.html.twig', ['candidat' => $candidat]);
    }
}
