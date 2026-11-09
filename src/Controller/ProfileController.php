<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use App\Repository\PropositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur HTTP "ProfileController".
 *
 * Routes :
 *  - GET /profil (app_profile)
 *  - GET /profil/connexions (app_login_history)
 *  - POST /profil/connexions/deconnecter-autres (app_logout_others)
 *  - GET,POST /profil/modifier (app_profile_edit)
 *  - GET,POST /profil/mot-de-passe (app_change_password)
 *  - POST /profil/supprimer (app_profile_delete)
 * Accès : ROLE_USER.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'app_profile', methods: ['GET'])]
    public function index(PropositionRepository $propositions, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $supports = (int) $em->createQuery('SELECT COUNT(v.id) FROM App\Entity\Vote v WHERE v.user = :u')->setParameter('u', $user)->getSingleScalarResult();
        $arguments = (int) $em->createQuery('SELECT COUNT(a.id) FROM App\Entity\Argument a WHERE a.author = :u')->setParameter('u', $user)->getSingleScalarResult();

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'propositions' => $propositions->findByAuthor($user),
            'supportCount' => $supports,
            'argumentCount' => $arguments,
            'myDebats' => $em->getRepository(\App\Entity\Debat::class)->findBy(['proposedBy' => $user], ['createdAt' => 'DESC'], 30),
        ]);
    }

    /** Historique des connexions du compte : permet de reperer un acces qui n'est pas le sien. */
    #[Route('/profil/connexions', name: 'app_login_history', methods: ['GET'])]
    public function logins(EntityManagerInterface $em): Response
    {
        $events = $em->getRepository(\App\Entity\LoginEvent::class)->findBy(['user' => $this->getUser()], ['id' => 'DESC'], 50);

        return $this->render('profile/logins.html.twig', ['events' => $events, 'user' => $this->getUser()]);
    }

    #[Route('/profil/connexions/deconnecter-autres', name: 'app_logout_others', methods: ['POST'])]
    public function logoutOthers(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('logout_others', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('app_login_history');
        }
        /** @var User $user */
        $user = $this->getUser();
        $user->revokeSessions();
        $em->flush();
        // Cet appareil-ci reste connecte : on rafraichit son horodatage de session.
        $request->getSession()->set(\App\EventSubscriber\LoginHistorySubscriber::SESSION_KEY, time() + 1);
        $this->addFlash('success', 'Tous les autres appareils sont déconnectés. Les cookies « rester connecté » existants sont aussi invalidés.');

        return $this->redirectToRoute('app_login_history');
    }

    #[Route('/profil/modifier', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->has('avatarFile') ? $form->get('avatarFile')->getData() : null;
            if ($avatarFile) {
                $dir = $this->getParameter('avatars_directory');
                $name = bin2hex(random_bytes(12)) . '.' . ($avatarFile->guessExtension() ?: 'jpg');
                try {
                    $avatarFile->move($dir, $name);
                    if ($user->getAvatarPath() && is_file($dir . '/' . $user->getAvatarPath())) {
                        @unlink($dir . '/' . $user->getAvatarPath());
                    }
                    $user->setAvatarPath($name);
                } catch (FileException) {
                    $this->addFlash('danger', 'L\'image n\'a pas pu être enregistrée.');
                }
            }
            $user->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Votre profil est à jour.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit.html.twig', ['form' => $form, 'user' => $user], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/profil/mot-de-passe', name: 'app_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $current = (string) $request->request->get('current_password');
            $new = (string) $request->request->get('new_password');
            $confirm = (string) $request->request->get('confirm_password');

            $error = match (true) {
                !$this->isCsrfTokenValid('change_password', (string) $request->request->get('_token')) => 'Session expirée. Réessayez.',
                !$hasher->isPasswordValid($user, $current) => 'Le mot de passe actuel est incorrect.',
                $new !== $confirm => 'Les deux nouveaux mots de passe ne sont pas identiques.',
                mb_strlen($new) < 10 => 'Le nouveau mot de passe doit contenir au moins 10 caractères.',
                default => null,
            };

            if ($error) {
                $this->addFlash('danger', $error);

                return $this->redirectToRoute('app_change_password');
            }

            $user->setPassword($hasher->hashPassword($user, $new));
            $em->flush();
            $this->addFlash('success', 'Votre mot de passe a été changé.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/change_password.html.twig');
    }

    /**
     * Droit a l'effacement : le compte est supprime. Les propositions et arguments publies
     * sont conserves mais anonymises (le lien vers l'auteur est coupe), les soutiens sont supprimes.
     */
    #[Route('/profil/supprimer', name: 'app_profile_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em, \App\Service\Account\AccountEraser $eraser): Response
    {
        if (!$this->isCsrfTokenValid('delete_account', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('app_profile');
        }

        /** @var User $user */
        $user = $this->getUser();
        $this->container->get('security.token_storage')->setToken(null);
        $request->getSession()->invalidate();

        $eraser->erase($user);

        $this->addFlash('success', 'Votre compte a été supprimé. Vos contributions publiées restent affichées sans votre nom.');

        return $this->redirectToRoute('app_home');
    }
}
