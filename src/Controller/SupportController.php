<?php

namespace App\Controller;

use App\Entity\InitiativeSupport;
use App\Entity\User;
use App\Service\DonationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page "Soutenir l'initiative" : toutes les façons d'aider La France en Débat, de la plus légère (déclarer son
 * soutien) à la plus engagée (donner du temps, du code, ou une contribution financière quand elle est ouverte).
 *
 *  - GET  /soutenir            la page, publique
 *  - POST /soutenir/rejoindre  déclarer son soutien (compte requis, un seul par compte, nom public facultatif)
 *  - POST /soutenir/retirer    retirer son soutien (la ligne est supprimée)
 *
 * Pour ajouter une façon d'aider, ajouter un bloc dans templates/support/index.html.twig.
 */
class SupportController extends AbstractController
{
    #[Route('/soutenir', name: 'app_support', methods: ['GET'])]
    public function index(EntityManagerInterface $em, DonationService $donations): Response
    {
        $repo = $em->getRepository(InitiativeSupport::class);
        $user = $this->getUser();
        $names = $em->createQuery('SELECT s.publicName FROM App\Entity\InitiativeSupport s WHERE s.publicName IS NOT NULL ORDER BY s.id DESC')->setMaxResults(24)->getSingleColumnResult();

        return $this->render('support/index.html.twig', [
            'total' => $repo->count([]),
            'names' => $names,
            'mine' => $user instanceof User ? $repo->findOneBy(['user' => $user]) : null,
            'donationsOpen' => $donations->isOpen(),
        ]);
    }

    #[Route('/soutenir/rejoindre', name: 'app_support_join', methods: ['POST'])]
    public function join(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->isCsrfTokenValid('support', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $repo = $em->getRepository(InitiativeSupport::class);
        if (!$repo->findOneBy(['user' => $user])) {
            $name = $request->request->getBoolean('public') ? (string) $request->request->get('public_name') : null;
            $em->persist(new InitiativeSupport($user, $name));
            $em->flush();
            $this->addFlash('success', 'Merci : votre soutien est enregistré.');
        }

        return $this->redirectToRoute('app_support', ['_fragment' => 'merci']);
    }

    #[Route('/soutenir/retirer', name: 'app_support_leave', methods: ['POST'])]
    public function leave(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$this->isCsrfTokenValid('support', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if ($mine = $em->getRepository(InitiativeSupport::class)->findOneBy(['user' => $user])) {
            $em->remove($mine);
            $em->flush();
            $this->addFlash('success', 'Votre soutien est retiré.');
        }

        return $this->redirectToRoute('app_support');
    }
}
