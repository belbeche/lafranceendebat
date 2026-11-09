<?php

namespace App\Controller;

use App\Repository\PropositionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Espaces dédiés par rôle : médiateur, élu, association. */
class RoleSpaceController extends AbstractController
{
    private const SPACES = [
        'mediateur' => ['role' => 'ROLE_MODERATOR', 'title' => 'Espace médiateur', 'intro' => 'Relire les contributions et garder le débat apaisé.'],
        'elu' => ['role' => 'ROLE_ELU', 'title' => 'Espace élu et institution', 'intro' => 'Suivre les propositions les plus soutenues et y répondre.'],
        'association' => ['role' => 'ROLE_ASSOCIATION', 'title' => 'Espace association', 'intro' => 'Porter des initiatives et suivre leur soutien.'],
    ];

    #[Route('/espace/{slug}', name: 'app_role_space', requirements: ['slug' => 'mediateur|elu|association'], methods: ['GET'])]
    public function index(string $slug, PropositionRepository $propositions): Response
    {
        $space = self::SPACES[$slug];
        if (!$this->isGranted($space['role'])) {
            $this->addFlash('warning', 'Cet espace est réservé aux comptes ayant ce droit. Vous pouvez le demander ici.');

            return $this->redirectToRoute('app_role_request');
        }

        return $this->render('hub/role_space.html.twig', [
            'space' => $space,
            'slug' => $slug,
            'pending' => $slug === 'mediateur' ? $propositions->findPending(10) : [],
            'top' => $propositions->findValidatedRanked(null, 10),
            'mine' => $propositions->findByAuthor($this->getUser()),
        ]);
    }
}
