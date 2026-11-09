<?php

namespace App\Controller;

use App\Entity\Revendication;
use App\Entity\User;
use App\Repository\PropositionRepository;
use App\Repository\RevendicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Espace contribuables : déposer une demande, la suivre, écrire aux acteurs. */
class ContribuableController extends AbstractController
{
    #[Route('/contribuables', name: 'app_taxpayers', methods: ['GET'])]
    public function index(RevendicationRepository $claims, PropositionRepository $propositions): Response
    {
        return $this->render('contribuables/index.html.twig', [
            'claims' => array_slice($claims->findPublished('Contribuables'), 0, 6),
            'others' => array_slice($claims->findPublished(), 0, 6),
        ]);
    }

    #[Route('/mes-demandes', name: 'app_my_requests', methods: ['GET'])]
    public function mine(EntityManagerInterface $em, PropositionRepository $propositions): Response
    {
        /** @var User $u */
        $u = $this->getUser();

        return $this->render('contribuables/mine.html.twig', [
            'claims' => $em->getRepository(Revendication::class)->findBy(['submittedBy' => $u], ['id' => 'DESC']),
            'propositions' => $propositions->findByAuthor($u),
            'debats' => $em->getRepository(\App\Entity\Debat::class)->findBy(['proposedBy' => $u], ['id' => 'DESC']),
            'events' => $em->getRepository(\App\Entity\Event::class)->findBy(['proposedBy' => $u], ['id' => 'DESC']),
        ]);
    }
}
