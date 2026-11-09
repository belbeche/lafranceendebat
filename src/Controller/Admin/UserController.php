<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur HTTP "UserController".
 *
 * Routes :
 *  - GET /admin/users/ (admin_users)
 *  - GET /admin/users/{id}/connexions (admin_user_logins)
 *  - POST /admin/users/{id}/toggle (admin_user_toggle)
 *  - POST /admin/users/{id}/delete (admin_user_delete)
 * Accès : ROLE_ADMIN.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('/', name: 'admin_users', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/users/index.html.twig', ['users' => $users->findBy([], ['id' => 'DESC'], 500)]);
    }

    #[Route('/{id}/connexions', name: 'admin_user_logins', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function logins(\App\Entity\User $user, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        return $this->render('admin/users/logins.html.twig', ['u' => $user, 'events' => $em->getRepository(\App\Entity\LoginEvent::class)->findBy(['user' => $user], ['id' => 'DESC'], 100)]);
    }

    /** Suspension ou reactivation d'un compte. Methode POST et jeton CSRF obligatoires. */
    #[Route('/{id}/toggle', name: 'admin_user_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->guard($user, $request, 'toggle')) {
            $user->setIsEnabled(!$user->isEnabled());
            $em->flush();
            $this->addFlash('success', $user->isEnabled() ? 'Compte réactivé.' : 'Compte suspendu.');
        }

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->guard($user, $request, 'delete')) {
            // Les contributions restent, anonymisees par la contrainte SET NULL de la base ; les soutiens sont supprimes.
            $em->createQuery('DELETE FROM App\Entity\Vote v WHERE v.user = :u')->setParameter('u', $user)->execute();
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Compte supprimé.');
        }

        return $this->redirectToRoute('admin_users');
    }

    private function guard(User $user, Request $request, string $action): bool
    {
        if (!$this->isCsrfTokenValid($action . 'user' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return false;
        }
        if ($user === $this->getUser()) {
            $this->addFlash('warning', 'Vous ne pouvez pas agir sur votre propre compte depuis ici.');

            return false;
        }

        return true;
    }
}
