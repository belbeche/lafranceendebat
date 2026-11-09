<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contrôleur HTTP "VolunteerController".
 *
 * Routes :
 *  - GET /benevolat (app_volunteer)
 */
class VolunteerController extends AbstractController
{
    #[Route('/benevolat', name: 'app_volunteer')]
    public function index(): Response
    {
        return $this->render('volunteer/index.html.twig');
    }
}
