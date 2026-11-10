<?php

namespace App\Controller\Admin;

use App\Entity\RefusalReason;
use App\Service\ModerationLocks;
use App\Service\RefusalReasons;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Motifs de refus de la moderation (suggestions dans chaque champ "Motif du refus") :
 *  - GET  /admin/motifs-refus
 *  - POST /admin/motifs-refus/ajouter | charger | {id}/renommer | {id}/basculer | {id}/monter | {id}/supprimer
 * Chaque action POST exige le jeton CSRF "reasons". Le motif saisi reste modifiable au cas par cas par le moderateur.
 */
#[Route('/admin/motifs-refus')]
#[IsGranted('ROLE_MODERATOR')]
class RefusalReasonAdminController extends AbstractController
{
    #[Route('', name: 'admin_reasons', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('admin/reasons/index.html.twig', [
            'items' => $em->getRepository(RefusalReason::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']),
            'defaults' => ModerationLocks::REASONS,
        ]);
    }

    #[Route('/{action}', name: 'admin_reasons_action', requirements: ['action' => 'ajouter|charger'], methods: ['POST'])]
    public function global(string $action, Request $request, EntityManagerInterface $em, RefusalReasons $reasons): Response
    {
        $this->csrf($request);
        if ('charger' === $action) {
            $n = $reasons->seedDefaults();
            $this->addFlash($n ? 'success' : 'warning', $n ? sprintf('%d motifs par défaut chargés. Vous pouvez les modifier.', $n) : 'La liste existe déjà.');

            return $this->redirectToRoute('admin_reasons');
        }
        $label = trim((string) $request->request->get('label'));
        if (mb_strlen($label) < 5 || mb_strlen($label) > 200) {
            $this->addFlash('danger', 'Motif : 5 à 200 caractères.');
        } else {
            $max = (int) $em->createQuery('SELECT COALESCE(MAX(r.position), 0) FROM App\Entity\RefusalReason r')->getSingleScalarResult();
            $em->persist(new RefusalReason($label, $max + 10));
            $em->flush();
            $this->addFlash('success', 'Motif ajouté.');
        }

        return $this->redirectToRoute('admin_reasons');
    }

    #[Route('/{id<\d+>}/{action}', name: 'admin_reason_action', requirements: ['action' => 'renommer|basculer|monter|supprimer'], methods: ['POST'])]
    public function item(RefusalReason $r, string $action, Request $request, EntityManagerInterface $em): Response
    {
        $this->csrf($request);
        switch ($action) {
            case 'renommer':
                $label = trim((string) $request->request->get('label'));
                if (mb_strlen($label) < 5 || mb_strlen($label) > 200) {
                    $this->addFlash('danger', 'Motif : 5 à 200 caractères.');

                    return $this->redirectToRoute('admin_reasons');
                }
                $r->setLabel($label);
                break;
            case 'basculer':
                $r->setActive(!$r->isActive());
                break;
            case 'monter':
                // On renumerote toute la liste (10, 20, 30...) apres l'echange : plus d'egalite possible entre deux motifs.
                $all = $em->getRepository(RefusalReason::class)->findBy([], ['position' => 'ASC', 'id' => 'ASC']);
                $i = array_search($r, $all, true);
                if (is_int($i) && $i > 0) {
                    [$all[$i - 1], $all[$i]] = [$all[$i], $all[$i - 1]];
                }
                foreach ($all as $k => $item) {
                    $item->setPosition(($k + 1) * 10);
                }
                break;
            case 'supprimer':
                $em->remove($r);
                break;
        }
        $em->flush();

        return $this->redirectToRoute('admin_reasons');
    }

    private function csrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('reasons', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }
    }
}
