<?php

namespace App\Controller;

use App\Entity\ActorEndorsement;
use App\Entity\Proposition;
use App\Entity\User;
use App\Service\ContentModerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Approbation motivée par les acteurs : chaque avis doit être argumenté, jamais un simple clic. */
class EndorsementController extends AbstractController
{
    public const ACTOR_ROLES = ['ROLE_ACTOR', 'ROLE_ELU', 'ROLE_ASSOCIATION', 'ROLE_MODERATOR'];

    #[Route('/propositions/{id}/avis-acteur', name: 'app_endorse', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function endorse(Proposition $proposition, Request $request, EntityManagerInterface $em, ContentModerator $moderator): Response
    {
        $user = $this->getUser();
        $back = $this->redirectToRoute('app_proposition_show', ['id' => $proposition->getId(), '_fragment' => 'avis-acteurs']);
        if (!$user instanceof User || !$this->isCsrfTokenValid('endorse'.$proposition->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Action refusée. Rechargez la page et réessayez.');

            return $back;
        }
        $allowed = false;
        foreach (self::ACTOR_ROLES as $r) {
            $allowed = $allowed || $this->isGranted($r);
        }
        if (!$allowed || !$proposition->isValidated()) {
            $this->addFlash('warning', 'Les avis d\'acteurs sont réservés aux comptes ayant un droit d\'acteur, sur les propositions publiées.');

            return $back;
        }
        $reason = trim((string) $request->request->get('reason'));
        if (mb_strlen($reason) < 30 || mb_strlen($reason) > 1500) {
            $this->addFlash('danger', 'Expliquez votre avis en 30 à 1500 caractères : un avis sans argument n\'est pas publié.');

            return $back;
        }
        if ($moderator->containsInsult($reason)) {
            $this->addFlash('danger', 'Votre texte contient des termes interdits par la charte de paix.');

            return $back;
        }
        $e = $em->getRepository(ActorEndorsement::class)->findOneBy(['proposition' => $proposition, 'user' => $user]) ?? new ActorEndorsement($proposition, $user);
        $e->setOpinion((string) $request->request->get('stance'), $reason);
        $em->persist($e);
        $em->flush();
        $this->addFlash('success', 'Votre avis est publié sur la proposition.');

        return $back;
    }
}
