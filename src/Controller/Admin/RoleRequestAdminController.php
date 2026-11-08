<?php

namespace App\Controller\Admin;

use App\Entity\RoleRequest;
use App\Entity\User;
use App\Repository\RoleRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur HTTP « RoleRequestAdminController ».
 *
 * Routes :
 *  - GET /admin/candidatures-droits (admin_role_requests)
 *  - POST /admin/candidatures-droits/{id<\d+>}/{decision} (admin_role_request_decide)
 * Accès : ROLE_ADMIN.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
#[Route('/admin/candidatures-droits')]
#[IsGranted('ROLE_ADMIN')]
class RoleRequestAdminController extends AbstractController
{
    #[Route('', name: 'admin_role_requests', methods: ['GET'])]
    public function index(RoleRequestRepository $repo): Response
    {
        return $this->render('admin/role_request/index.html.twig', ['pending' => $repo->pending(), 'recent' => $repo->recent()]);
    }

    #[Route('/{id<\d+>}/{decision}', name: 'admin_role_request_decide', requirements: ['decision' => 'approve|reject|revoke'], methods: ['POST'])]
    public function decide(RoleRequest $rr, string $decision, Request $request, EntityManagerInterface $em, \App\Service\TransactionalMailer $mails): Response
    {
        $back = $this->redirectToRoute('admin_role_requests');
        if (!$this->isCsrfTokenValid('role_request_decide' . $rr->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée.');

            return $back;
        }
        /** @var User $admin */
        $admin = $this->getUser();
        $target = $rr->getUser();
        $role = $rr->getRole();
        if (!isset(RoleRequest::ROLES[$role]) || $role === 'ROLE_ADMIN' || $role === 'ROLE_SUPER_ADMIN') {
            throw $this->createAccessDeniedException();
        }
        if ($target->getId() === $admin->getId()) {
            $this->addFlash('danger', 'Vous ne pouvez pas traiter votre propre candidature.');

            return $back;
        }
        $note = (string) $request->request->get('note', '');

        if ($decision === 'approve' && $rr->isPending()) {
            $roles = $target->getRoles();
            $roles = array_values(array_unique(array_diff(array_merge($roles, [$role]), ['ROLE_USER'])));
            $target->setRoles($roles);
            $rr->decide(RoleRequest::STATUS_APPROVED, $admin, $note);
            $this->addFlash('success', 'Droit accordé à ' . $target->getPublicName() . '.');
        } elseif ($decision === 'reject' && $rr->isPending()) {
            $rr->decide(RoleRequest::STATUS_REJECTED, $admin, $note);
            $this->addFlash('info', 'Candidature refusée.');
        } elseif ($decision === 'revoke' && $rr->getStatus() === RoleRequest::STATUS_APPROVED) {
            $target->setRoles(array_values(array_diff($target->getRoles(), [$role, 'ROLE_USER'])));
            $rr->decide(RoleRequest::STATUS_REVOKED, $admin, $note);
            $this->addFlash('info', 'Droit retiré.');
        } else {
            $this->addFlash('warning', 'Action impossible dans cet état.');

            return $back;
        }
        $em->flush();
        $mails->roleDecision($rr);

        return $back;
    }
}
