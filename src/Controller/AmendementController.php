<?php

namespace App\Controller;

use App\Entity\Amendement;
use App\Entity\Proposition;
use App\Entity\User;
use App\Service\ContentModerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Contrôleur HTTP « AmendementController ».
 *
 * Routes :
 *  - POST /propositions/{id}/amender (app_amendement_new)
 * Accès : ROLE_USER.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 * Tout texte public passe par ContentModerator avant d'être enregistré.
 */
#[IsGranted('ROLE_USER')]
class AmendementController extends AbstractController
{
    #[Route('/propositions/{id}/amender', name: 'app_amendement_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function new(
        Proposition $proposition,
        Request $request,
        EntityManagerInterface $em,
        ContentModerator $moderator,
        ValidatorInterface $validator,
    ): Response {
        if (!$proposition->isValidated()) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('amend' . $proposition->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('app_proposition_show', ['id' => $proposition->getId()]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $amendement = (new Amendement())
            ->setProposition($proposition)
            ->setAuthor($user)
            ->setContent((string) $request->request->get('content'))
            ->setJustification((string) $request->request->get('justification'));

        $errors = $validator->validate($amendement);
        if (count($errors) > 0) {
            $this->addFlash('danger', $errors[0]->getMessage());

            return $this->redirectToRoute('app_proposition_show', ['id' => $proposition->getId(), '_fragment' => 'amendements']);
        }

        $reasons = $moderator->analyze($amendement->getContent() . "\n" . $amendement->getJustification());
        if ($reasons !== []) {
            $this->addFlash('danger', $moderator->explain($reasons));

            return $this->redirectToRoute('app_proposition_show', ['id' => $proposition->getId(), '_fragment' => 'amendements']);
        }

        $em->persist($amendement);
        $em->flush();
        $this->addFlash('success', 'Votre amendement est enregistré. Il sera visible après relecture par un modérateur.');

        return $this->redirectToRoute('app_proposition_show', ['id' => $proposition->getId(), '_fragment' => 'amendements']);
    }
}
