<?php

namespace App\Controller;

use App\Entity\Proposition;
use App\Entity\ServiceOrder;
use App\Entity\User;
use App\Repository\PropositionRepository;
use App\Repository\ServiceOrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Page d'accueil connectee : gros boutons par besoin, suivi de ma proposition, taches a faire, espaces par role. */
#[IsGranted('ROLE_USER')]
class HubController extends AbstractController
{
    public const STEPS = ['Déposée', 'Analyse en cours', 'Publiée au registre', 'Portée aux élus', 'Suivi'];

    #[Route('/mon-espace', name: 'app_hub', methods: ['GET'])]
    public function __invoke(PropositionRepository $propositions, ServiceOrderRepository $orders): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $mine = $propositions->findByAuthor($user);
        $last = $mine[0] ?? null;
        $current = 0;
        if ($last instanceof Proposition) {
            $current = $last->isValidated() ? 3 : ($last->getStatus() === Proposition::STATUS_REJECTED ? 0 : 2);
        }

        $todo = [];
        foreach ($orders->forBuyer($user) as $o) {
            if ($o->getStatus() === ServiceOrder::PAID) {
                $todo[] = ['label' => 'Confirmer la prestation « '.$o->getOffer()->getTitle().' » une fois réalisée', 'button' => 'Voir', 'url' => $this->generateUrl('app_my_orders'), 'red' => true];
            }
        }
        if ($this->isGranted('ROLE_ACTOR')) {
            foreach ($orders->forProvider($user) as $o) {
                if ($o->getStatus() === ServiceOrder::PAID) {
                    $todo[] = ['label' => 'Réaliser la prestation « '.$o->getOffer()->getTitle().' »', 'button' => 'Ouvrir', 'url' => $this->generateUrl('app_actor_space'), 'red' => false];
                }
            }
        }
        if ($this->isGranted('ROLE_MODERATOR') && ($n = count($propositions->findPending(50))) > 0) {
            $todo[] = ['label' => $n.' contribution(s) à relire', 'button' => 'Modérer', 'url' => $this->generateUrl('admin_moderation'), 'red' => false];
        }
        if ($last instanceof Proposition && $last->getStatus() === Proposition::STATUS_REJECTED) {
            $todo[] = ['label' => 'Votre proposition « '.$last->getTitle().' » a été refusée, vous pouvez la reformuler', 'button' => 'Reformuler', 'url' => $this->generateUrl('app_proposition_new'), 'red' => false];
        }

        return $this->render('hub/index.html.twig', ['last' => $last, 'current' => $current, 'steps' => self::STEPS, 'todo' => $todo]);
    }
}
