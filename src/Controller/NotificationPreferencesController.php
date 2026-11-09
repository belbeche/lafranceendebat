<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Preferences e-mail : chacun choisit, et peut tout couper en un clic depuis n'importe quel e-mail. */
class NotificationPreferencesController extends AbstractController
{
    #[Route('/profil/preferences-email', name: 'app_notif_prefs', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function prefs(Request $request, EntityManagerInterface $em, \App\Service\Lettre\LettreOptIn $lettre): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('notif_prefs', (string) $request->request->get('_token'))) {
                $user->setNotifyOwnActivity($request->request->getBoolean('own'))->setNotifyNewDebates($request->request->getBoolean('debates'));
                $em->flush();
                $wantsLetter = $request->request->getBoolean('lettre');
                $current = $lettre->status($user);
                if ($wantsLetter && 'confirmed' !== $current) {
                    $r = $lettre->subscribe($user);
                    $this->addFlash('success', 'confirmed' === $r ? 'Vous êtes abonné à la lettre.' : 'Préférences enregistrées. Un e-mail de confirmation vous a été envoyé pour la lettre : cliquez sur le lien pour valider.');
                } else {
                    if (!$wantsLetter && 'none' !== $current) {
                        $lettre->unsubscribe($user);
                    }
                    $this->addFlash('success', 'Vos préférences e-mail sont enregistrées.');
                }
            } else {
                $this->addFlash('danger', 'Session expirée, réessayez.');
            }

            return $this->redirectToRoute('app_notif_prefs');
        }

        return $this->render('profile/notifications.html.twig', ['user' => $user, 'lettre' => $lettre->status($user)]);
    }

    /** Lien signe present dans chaque e-mail de notification : coupe tous les e-mails de notification sans connexion. */
    #[Route('/desabonnement-email/{id}', name: 'app_notif_unsubscribe', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function unsubscribe(int $id, Request $request, UriSigner $signer, EntityManagerInterface $em): Response
    {
        if (!$signer->checkRequest($request)) {
            throw $this->createNotFoundException();
        }
        $user = $em->getRepository(User::class)->find($id) ?? throw $this->createNotFoundException();
        $user->setNotifyOwnActivity(false)->setNotifyNewDebates(false);
        $em->flush();

        return $this->render('profile/notifications_unsubscribed.html.twig');
    }
}
