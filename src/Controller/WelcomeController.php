<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Portail d'accueil : guide chaque personne dès son arrivée vers ses trois premières actions. */
class WelcomeController extends AbstractController
{
    public const COOKIE = 'lfed_welcome';

    /** profil => [icône, [[route, paramètres, clé de libellé]...]] */
    private const PROFILES = [
        'citoyen' => ['person', [['app_debats', [], 'guide.debats.cta'], ['app_scrutins', [], 'guide.scrutins.cta'], ['app_proposition_new', [], 'guide.propositions.cta'], ['app_community', [], 'home.live.join']]],
        'contribuable' => ['piggy-bank', [['app_plan_marshall', ['_fragment' => 'agir'], 'eco.cta2'], ['app_revendication_new', [], 'claims.cta'], ['app_useful_contacts', [], 'nav.contacts'], ['app_propositions', [], 'tax.c3t']]],
        'association' => ['people', [['app_role_request', ['role' => 'ROLE_ASSOCIATION'], 'guide.candidater.cta'], ['app_plan_marshall', ['_fragment' => 'agir'], 'eco.cta2'], ['app_proposition_new', [], 'guide.propositions.cta'], ['app_services', [], 'guide.services.cta']]],
        'entreprise' => ['building', [['app_plan_marshall', ['_fragment' => 'agir'], 'eco.cta2'], ['app_services', [], 'guide.services.cta'], ['app_role_request', ['role' => 'ROLE_ACTOR'], 'guide.candidater.cta'], ['app_proposition_new', [], 'guide.propositions.cta']]],
        'elu' => ['bank', [['app_role_request', ['role' => 'ROLE_ELU'], 'guide.candidater.cta'], ['app_propositions', [], 'tax.c3t'], ['app_institutional_routes', [], 'guide.acteurs.cta'], ['app_scrutins', [], 'guide.scrutins.cta']]],
        'expert' => ['briefcase', [['app_role_request', ['role' => 'ROLE_ACTOR'], 'guide.candidater.cta'], ['app_services', [], 'guide.services.cta'], ['app_plan_marshall', ['_fragment' => 'agir'], 'eco.cta2'], ['app_guide', [], 'nav.guide']]],
    ];

    #[Route('/bienvenue', name: 'app_welcome', methods: ['GET'])]
    public function index(): Response
    {
        return $this->mark($this->render('welcome/index.html.twig', ['profiles' => self::PROFILES]));
    }

    #[Route('/bienvenue/plus-tard', name: 'app_welcome_later', methods: ['GET'])]
    public function later(): Response
    {
        return $this->mark(new RedirectResponse($this->generateUrl('app_home')));
    }

    #[Route('/bienvenue/{profile}', name: 'app_welcome_profile', requirements: ['profile' => 'citoyen|contribuable|association|entreprise|elu|expert'], methods: ['GET'])]
    public function profile(string $profile): Response
    {
        return $this->mark($this->render('welcome/profile.html.twig', ['profile' => $profile, 'icon' => self::PROFILES[$profile][0], 'actions' => self::PROFILES[$profile][1]]));
    }

    #[Route('/application', name: 'app_install', methods: ['GET'])]
    public function install(): Response
    {
        return $this->render('welcome/install.html.twig');
    }

    private function mark(Response $r): Response
    {
        $r->headers->setCookie(Cookie::create(self::COOKIE, '1', new \DateTimeImmutable('+1 year'), '/', null, null, true, false, Cookie::SAMESITE_LAX));

        return $r;
    }
}
