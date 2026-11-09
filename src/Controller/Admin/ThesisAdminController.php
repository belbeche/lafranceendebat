<?php

namespace App\Controller\Admin;

use App\Entity\Thesis;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/analyses')]
#[IsGranted('ROLE_ADMIN')]
class ThesisAdminController extends AbstractController
{
    #[Route('', name: 'admin_theses', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('admin/theses/index.html.twig', ['items' => $em->getRepository(Thesis::class)->findBy([], ['id' => 'DESC'])]);
    }

    #[Route('/{id<\d+>}/approuver', name: 'admin_thesis_toggle', methods: ['POST'])]
    public function toggle(Request $r, Thesis $t, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('thesis_admin', (string) $r->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $t->setApproved(!$t->isApproved());
        $em->flush();

        return $this->redirectToRoute('admin_theses');
    }
}
