<?php

namespace App\Controller;

use App\Entity\Transmission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Pages editoriales : methode, charte, consultations, voies institutionnelles, Plan Marshall citoyen. */
class PageController extends AbstractController
{
    #[Route('/comment-ca-marche', name: 'app_how_it_works', methods: ['GET'])]
    public function howItWorks(): Response
    {
        return $this->render('pages/how_it_works.html.twig', ['tiers' => \App\Service\RankingService::TIERS]);
    }

    /** Page « Code source » : exigée par l'esprit de l'AGPL-3.0 (les utilisateurs du service doivent pouvoir obtenir le code). */
    #[Route('/code-source', name: 'app_open_source', methods: ['GET'])]
    public function openSource(): Response
    {
        return $this->render('pages/open_source.html.twig', ['repo' => 'https://github.com/belbeche/lafranceendebat']);
    }

    #[Route('/marche-a-suivre', name: 'app_guide', methods: ['GET'])]
    public function guide(): Response
    {
        return $this->render('pages/guide.html.twig');
    }

    #[Route('/contacts-utiles', name: 'app_useful_contacts', methods: ['GET'])]
    public function usefulContacts(): Response
    {
        return $this->render('pages/contacts.html.twig');
    }

    #[Route('/forces-politiques', name: 'app_political_forces', methods: ['GET'])]
    public function politicalForces(): Response
    {
        return $this->render('pages/political_forces.html.twig');
    }

    #[Route('/charte-de-paix', name: 'app_charter', methods: ['GET'])]
    public function charter(): Response
    {
        return $this->render('pages/charter.html.twig');
    }

    #[Route('/notice-identite', name: 'app_identity_notice', methods: ['GET'])]
    public function identityNotice(): Response
    {
        return $this->render('pages/identity_notice.html.twig');
    }

    #[Route('/methode', name: 'app_method', methods: ['GET'])]
    public function method(): Response
    {
        return $this->render('pages/method.html.twig');
    }

    #[Route('/consultations-et-votes', name: 'app_consultations', methods: ['GET'])]
    public function consultations(): Response
    {
        return $this->render('pages/consultations.html.twig');
    }

    #[Route('/voies-institutionnelles', name: 'app_institutional_routes', methods: ['GET'])]
    public function institutionalRoutes(): Response
    {
        return $this->render('pages/institutional_routes.html.twig', ['routes' => Transmission::ROUTES]);
    }

    #[Route('/plan-marshall-citoyen', name: 'app_plan_marshall', methods: ['GET'])]
    public function planMarshall(): Response
    {
        return $this->render('pages/plan_marshall.html.twig');
    }
}
