<?php

namespace App\Controller\Admin;

use App\Entity\AnonymousContribution;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Relecture des contributions anonymes : GET /admin/contributions-anonymes, POST /{id}/statut (CSRF obligatoire). */
#[Route('/admin/contributions-anonymes')]
#[IsGranted('ROLE_ADMIN')]
class AnonymousContributionAdminController extends AbstractController
{
    #[Route('', name: 'admin_anon_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('admin/anonymous/index.html.twig', ['items' => $em->getRepository(AnonymousContribution::class)->findBy([], ['id' => 'DESC'], 100)]);
    }

    #[Route('/{id<\d+>}/statut', name: 'admin_anon_status', methods: ['POST'])]
    public function status(Request $request, AnonymousContribution $c, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('anon_status' . $c->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $c->setStatus((string) $request->request->get('status'));
        $em->flush();

        return $this->redirectToRoute('admin_anon_index');
    }
}
