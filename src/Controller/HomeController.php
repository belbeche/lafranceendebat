<?php

namespace App\Controller;

use App\Entity\Proposition;
use App\Repository\ConsultationRepository;
use App\Repository\DebatRepository;
use App\Repository\DiscussionRepository;
use App\Repository\EventRepository;
use App\Repository\MissionRepository;
use App\Repository\PostRepository;
use App\Repository\PropositionRepository;
use App\Repository\RevendicationRepository;
use App\Repository\TransmissionRepository;
use App\Repository\UserRepository;
use App\Repository\VoteRepository;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP "HomeController".
 *
 * Routes :
 *  - GET / (app_home)
 *  - GET /politique-de-confidentialite (app_privacy_policy)
 *  - GET /cookie-preferences (cookie_preferences)
 *  - GET /conditions-utilisation (app_terms_of_service)
 *  - GET /conditions-place-de-marche (app_marketplace_terms)
 *  - GET /mentions-legales (app_legal_notice)
 *  - GET /a-propos (app_about)
 *  - GET /faq (app_faq)
 */
class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        \Symfony\Component\HttpFoundation\Request $request,
        PropositionRepository $propositions,
        VoteRepository $votes,
        DebatRepository $debats,
        EventRepository $events,
        RevendicationRepository $revendications,
        TransmissionRepository $transmissions,
        MissionRepository $missions,
        PostRepository $posts,
        UserRepository $users,
        \App\Service\RankingService $ranking,
        \App\Repository\ServiceOfferRepository $offers,
        DiscussionRepository $discussions,
        ConsultationRepository $consultations,
    ): Response {
        $top = $propositions->findValidatedRanked(null, 6);
        $user = $this->getUser();

        return $this->render('home/index.html.twig', [
            'topPropositions' => $top,
            'pillars' => $missions->findForHome(),
            'paidMissions' => \App\Controller\MissionsController::paidHighlights(4),
            'topOffers' => array_slice($offers->published(), 0, 3),
            'peacePodium' => $ranking->top('mois', 3),
            'supportedIds' => $votes->findSupportedIds($user instanceof User ? $user : null, $top),
            'propositionCount' => $propositions->countValidated(),
            'openDebats' => $debats->findOpen(3),
            'upcomingEvents' => $events->findUpcoming(3),
            'forgotten' => array_slice($revendications->findPublishedForReader($request->getLocale()), 0, 4),
            'awaitingCount' => $revendications->countAwaitingAnswer(),
            'recentTransmissions' => array_slice($transmissions->findAllPublic(), 0, 3),
            'latestPosts' => $posts->findBy(['isPublished' => true], ['createdAt' => 'DESC'], 3),
            'memberCount' => $users->count([]),
            'latestDiscussions' => $discussions->findPublic(null, 4),
            'discussionCount' => $discussions->count([]),
            'openScrutins' => count($consultations->findOpen()),
            'themes' => Proposition::THEMES,
        ]);
    }

    #[Route('/politique-de-confidentialite', name: 'app_privacy_policy')]
    public function privacyPolicy(): Response
    {
        return $this->render('legal/privacy_policy.html.twig');
    }

    #[Route('/cookie-preferences', name: 'cookie_preferences')]
    public function cookiePreferences(): Response
    {
        return $this->render('legal/cookie_preferences.html.twig', ['consentVersion' => \App\Service\Audience\ConsentManager::VERSION, 'consentMonths' => \App\Service\Audience\ConsentManager::MONTHS]);
    }

    #[Route('/conditions-utilisation', name: 'app_terms_of_service')]
    public function termsOfService(): Response
    {
        return $this->render('legal/terms_of_service.html.twig');
    }

    #[Route('/conditions-place-de-marche', name: 'app_marketplace_terms')]
    public function marketplaceTerms(): Response
    {
        return $this->render('legal/marketplace_terms.html.twig');
    }

    #[Route('/mentions-legales', name: 'app_legal_notice')]
    public function legalNotice(): Response
    {
        return $this->render('legal/legal_notice.html.twig');
    }

    #[Route('/a-propos', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('about/index.html.twig');
    }

    #[Route('/faq', name: 'app_faq')]
    public function faq(): Response
    {
        return $this->render('home/faq.html.twig');
    }
}
