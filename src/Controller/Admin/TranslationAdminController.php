<?php

namespace App\Controller\Admin;

use App\Repository\ContentTranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur HTTP « TranslationAdminController ».
 *
 * Routes :
 *  - GET /admin/traductions (admin_translations)
 *  - POST /admin/traductions/{id<\d+>}/{decision} (admin_translation_decide)
 * Accès : ROLE_MODERATOR.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
#[IsGranted('ROLE_MODERATOR')]
#[Route('/admin/traductions')]
class TranslationAdminController extends AbstractController
{
    #[Route('', name: 'admin_translations', methods: ['GET'])]
    public function index(ContentTranslationRepository $repo): Response
    {
        return $this->render('admin/translations/index.html.twig', ['items' => $repo->pending()]);
    }

    #[Route('/{id<\d+>}/{decision}', name: 'admin_translation_decide', requirements: ['decision' => 'approve|reject'], methods: ['POST'])]
    public function decide(int $id, string $decision, Request $request, ContentTranslationRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('translation_decide' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée.');

            return $this->redirectToRoute('admin_translations');
        }
        $t = $repo->find($id);
        if ($t && !$t->isPublished()) {
            if ('approve' === $decision) {
                $t->publish();
            } else {
                $em->remove($t);
            }
            $em->flush();
            $this->addFlash('success', 'approve' === $decision ? 'Traduction publiée.' : 'Traduction refusée.');
        }

        return $this->redirectToRoute('admin_translations');
    }
}
