<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\RankingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur HTTP "RankingController".
 *
 * Routes :
 *  - GET /classement (app_ranking)
 *  - POST /classement/participation (app_ranking_optin)
 * Accès : ROLE_USER.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
class RankingController extends AbstractController
{
    #[Route('/classement', name: 'app_ranking', methods: ['GET'])]
    public function index(Request $request, RankingService $ranking, \App\Repository\PropositionRepository $props): Response
    {
        $view = in_array($request->query->get('vue'), ['propositions', 'tendances', 'petitions'], true) ? (string) $request->query->get('vue') : 'paix';
        $period = $request->query->get('periode') === 'tout' ? 'tout' : 'mois';

        return $this->render('ranking/index.html.twig', [
            'view' => $view,
            'rows' => $view === 'paix' ? $ranking->top($period) : [],
            'items' => match ($view) {
                'propositions' => array_map(static fn ($p) => ['p' => $p, 'n' => $p->getSupportCount()], $props->findValidatedRanked(null, 30)),
                'tendances' => $props->findTrending(),
                'petitions' => array_map(static fn ($p) => ['p' => $p, 'n' => $p->getSupportCount()], $props->findPetitions()),
                default => [],
            },
            'period' => $period,
            'me' => $this->getUser() instanceof User ? $ranking->progress($this->getUser()) : null,
            'tiers' => RankingService::TIERS,
            'thanksCap' => RankingService::THANKS_CAP,
            'refCap' => RankingService::REFORMULATION_CAP,
        ]);
    }

    #[Route('/classement/participation', name: 'app_ranking_optin', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function optIn(Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('ranking_optin', (string) $request->request->get('_token'))) {
            /** @var User $user */
            $user = $this->getUser();
            $user->setRankingOptIn(!$user->isRankingOptIn());
            $em->flush();
            $this->addFlash('success', $user->isRankingOptIn()
                ? 'Vous apparaissez désormais dans le classement (prénom et initiale uniquement).'
                : 'Vous n\'apparaissez plus dans le classement.');
        } else {
            $this->addFlash('danger', 'Session expirée. Réessayez.');
        }

        return $this->redirectToRoute('app_ranking');
    }
}
