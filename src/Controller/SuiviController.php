<?php

namespace App\Controller;

use App\Repository\TransmissionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP "SuiviController".
 *
 * Routes :
 *  - GET /suivi-institutionnel (app_suivi)
 */
class SuiviController extends AbstractController
{
    #[Route('/suivi-institutionnel', name: 'app_suivi', methods: ['GET'])]
    public function index(TransmissionRepository $transmissions): Response
    {
        return $this->render('suivi/index.html.twig', ['transmissions' => $transmissions->findAllPublic()]);
    }
}
