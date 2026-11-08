<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\Account\AccountEraser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;

/** Reponse a l'avis d'inactivite : lien signe recu par e-mail, deux boutons (aucune action sur simple ouverture du lien). */
class InactivityController extends AbstractController
{
    #[Route('/compte/inactivite/{id}', name: 'app_inactivity', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function answer(int $id, Request $request, EntityManagerInterface $em, UriSigner $signer, AccountEraser $eraser): Response
    {
        $user = $em->getRepository(User::class)->find($id);
        if (!$user || !$signer->checkRequest($request)) {
            return $this->render('account/inactivity.html.twig', ['state' => 'invalid'], new Response(status: 403));
        }
        if (null === $user->getInactivityNoticeAt()) {
            return $this->render('account/inactivity.html.twig', ['state' => 'none']);
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('inactivity' . $id, (string) $request->request->get('_token'))) {
                return $this->render('account/inactivity.html.twig', ['state' => 'invalid'], new Response(status: 403));
            }
            if ('delete' === $request->request->get('choice')) {
                $eraser->erase($user);

                return $this->render('account/inactivity.html.twig', ['state' => 'deleted']);
            }
            $user->setInactivityNoticeAt(null)->setLastLogin(new \DateTimeImmutable());
            $em->flush();

            return $this->render('account/inactivity.html.twig', ['state' => 'kept']);
        }

        return $this->render('account/inactivity.html.twig', ['state' => 'ask', 'user' => $user]);
    }
}
