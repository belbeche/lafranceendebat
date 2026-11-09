<?php

namespace App\Controller;

use App\Entity\Ballot;
use App\Entity\Consultation;
use App\Entity\User;
use App\Repository\BallotRepository;
use App\Repository\ConsultationRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Scrutins consultatifs. Les resultats sont des avis du reseau, jamais un vote officiel. */
class ConsultationController extends AbstractController
{
    #[Route('/scrutins', name: 'app_scrutins', methods: ['GET'])]
    public function index(ConsultationRepository $repo): Response
    {
        return $this->render('consultation/index.html.twig', ['open' => $repo->findOpen(), 'closed' => $repo->findClosed()]);
    }

    #[Route('/scrutins/{id<\d+>}', name: 'app_consultation_show', methods: ['GET'])]
    public function show(Consultation $consultation, BallotRepository $ballots): Response
    {
        $user = $this->getUser();
        $mine = $user instanceof User ? $ballots->findOneFor($consultation, $user) : null;
        $showResults = $mine !== null || $consultation->isClosed();
        $tally = $showResults ? $ballots->tally($consultation) : [];

        return $this->render('consultation/show.html.twig', [
            'c' => $consultation, 'mine' => $mine, 'showResults' => $showResults,
            'tally' => $tally, 'total' => array_sum($tally),
        ]);
    }

    #[Route('/scrutins/{id<\d+>}/voter', name: 'app_consultation_vote', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function vote(Consultation $consultation, Request $request, BallotRepository $ballots, EntityManagerInterface $em): Response
    {
        $back = $this->redirectToRoute('app_consultation_show', ['id' => $consultation->getId()]);
        if (!$this->isCsrfTokenValid('ballot' . $consultation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $back;
        }
        $choice = filter_var($request->request->get('choice'), FILTER_VALIDATE_INT);
        if ($choice === false || $choice < 0 || $choice >= count($consultation->getOptions())) {
            $this->addFlash('danger', 'Choisissez une des réponses proposées.');

            return $back;
        }
        if (!$consultation->isOpen()) {
            $this->addFlash('warning', 'Ce scrutin n\'est pas ouvert.');

            return $back;
        }
        /** @var User $user */
        $user = $this->getUser();
        if ($ballots->findOneFor($consultation, $user)) {
            $this->addFlash('info', 'Vous avez déjà participé à ce scrutin. Un seul avis par personne.');

            return $back;
        }
        try {
            $em->persist(new Ballot($consultation, $user, $choice));
            $em->flush();
            $this->addFlash('success', 'Votre avis est enregistré. Il est consultatif et ne modifie pas ce choix pour les autres.');
        } catch (UniqueConstraintViolationException) {
            $this->addFlash('info', 'Vous avez déjà participé à ce scrutin.');
        }

        return $back;
    }
}
