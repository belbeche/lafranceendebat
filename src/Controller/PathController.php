<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Parcours guides par situation : chaque scenario mene pas a pas vers la bonne page. */
class PathController extends AbstractController
{
    /** slug => [icone, [[route, compte requis], ...]] */
    private const PATHS = [
        'discover' => ['bi-compass', [['app_charter', false], ['app_propositions', false], ['app_revendications', false]]],
        'debate' => ['bi-chat-square-text', [['app_debats', false], ['app_register', false], ['app_debats', true]]],
        'propose' => ['bi-lightbulb', [['app_propositions', false], ['app_register', false], ['app_proposition_new', true]]],
        'forgotten' => ['bi-bookmark-star', [['app_revendications', false], ['app_propositions', true], ['app_institutional_routes', false]]],
        'follow' => ['bi-binoculars', [['app_suivi', false], ['app_candidats', false], ['app_agenda', false]]],
    ];

    #[Route('/parcours', name: 'app_paths', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('path/index.html.twig', ['paths' => self::PATHS]);
    }

    #[Route('/parcours/{slug}', name: 'app_path', requirements: ['slug' => 'discover|debate|propose|forgotten|follow'], methods: ['GET'])]
    public function show(string $slug): Response
    {
        [$icon, $steps] = self::PATHS[$slug];

        return $this->render('path/show.html.twig', ['slug' => $slug, 'icon' => $icon, 'steps' => $steps]);
    }
}
