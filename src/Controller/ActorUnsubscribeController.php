<?php

namespace App\Controller;

use App\Entity\ActorContact;
use App\Service\Lettre\LettreTokens;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP « ActorUnsubscribeController ».
 *
 * Routes :
 *  - GET,POST /acteurs/desinscription/{id<\d+>}/{token} (app_actor_unsubscribe)
 *  - GET,POST /acteurs/accepter/{id<\d+>}/{token} (app_actor_consent)
 */
class ActorUnsubscribeController extends AbstractController
{
    /** GET : page de confirmation. POST : desinscription immediate et definitive (compatible List-Unsubscribe-Post, sans session). */
    #[Route('/acteurs/desinscription/{id<\d+>}/{token}', name: 'app_actor_unsubscribe', methods: ['GET', 'POST'])]
    public function __invoke(int $id, string $token, Request $request, EntityManagerInterface $em, LettreTokens $tokens): Response
    {
        $a = $em->find(ActorContact::class, $id);
        if (null === $a || !hash_equals($tokens->actorUnsubscribe($id, $a->getEmail()), $token)) {
            return $this->render('lettre/result.html.twig', ['ok' => false, 'message' => 'Ce lien de désinscription est invalide.'], new Response('', 400));
        }
        if ($request->isMethod('POST')) {
            $a->unsubscribe();
            $em->flush();

            return $this->render('lettre/result.html.twig', ['ok' => true, 'message' => 'C\'est fait : votre adresse ne sera plus jamais contactée.']);
        }

        return $this->render('actors/unsubscribe.html.twig', ['id' => $id, 'token' => $token]);
    }

    /** Consentement explicite : seul ce clic (confirme par POST) autorise des messages suivants. */
    #[Route('/acteurs/accepter/{id<\d+>}/{token}', name: 'app_actor_consent', methods: ['GET', 'POST'])]
    public function consent(int $id, string $token, Request $request, EntityManagerInterface $em, LettreTokens $tokens): Response
    {
        $a = $em->find(ActorContact::class, $id);
        if (null === $a || !hash_equals($tokens->actorConsent($id, $a->getEmail()), $token)) {
            return $this->render('lettre/result.html.twig', ['ok' => false, 'message' => 'Ce lien est invalide.'], new Response('', 400));
        }
        if ($a->getStatus() !== ActorContact::ACTIVE) {
            return $this->render('lettre/result.html.twig', ['ok' => false, 'message' => 'Cette adresse s\'est désinscrite : aucun message ne sera envoyé.']);
        }
        if ($request->isMethod('POST')) {
            $a->giveConsent();
            $em->flush();

            return $this->render('lettre/result.html.twig', ['ok' => true, 'message' => 'Merci. Vous recevrez nos informations (au plus une par semaine). Chaque message contient un lien pour vous retirer à tout moment.']);
        }

        return $this->render('actors/consent.html.twig', ['id' => $id, 'token' => $token, 'actor' => $a]);
    }
}
