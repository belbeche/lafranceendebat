<?php

namespace App\Controller\Admin;

use App\Service\Approval\ApprovalRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * File unique de validation : tout ce qui attend une décision humaine, toutes fonctionnalités confondues,
 * avec un bouton par ligne. Les contenus rédigés par l'IA restent étiquetés. La liste vient des
 * ApprovalProvider : une nouvelle fonctionnalité s'y branche sans toucher à ce contrôleur.
 */
#[Route('/admin/a-valider')]
#[IsGranted('ROLE_ADMIN')]
class ApprovalController extends AbstractController
{
    #[Route('', name: 'admin_approvals', methods: ['GET'])]
    public function index(ApprovalRegistry $registry): Response
    {
        $groups = [];
        foreach ($registry->all() as $kind => $p) {
            $groups[] = ['kind' => $kind, 'label' => $p->label(), 'count' => $p->count(), 'items' => $p->pending(50)];
        }

        return $this->render('admin/approvals/index.html.twig', ['groups' => $groups, 'total' => array_sum(array_column($groups, 'count'))]);
    }

    #[Route('/{kind}/{id<\d+>}/{decision<approuver|refuser>}', name: 'admin_approval_decide', methods: ['POST'])]
    public function decide(Request $r, ApprovalRegistry $registry, string $kind, int $id, string $decision): Response
    {
        if (!$this->isCsrfTokenValid('approvals', (string) $r->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $p = $registry->get($kind);
        if (!$p) {
            throw $this->createNotFoundException();
        }
        $ok = 'approuver' === $decision ? $p->approve($id) : $p->reject($id);
        $this->addFlash($ok ? 'success' : 'warning', $ok ? ('approuver' === $decision ? 'Approuvé.' : 'Refusé.') : 'Rien à faire : déjà traité ou refus impossible pour ce type.');

        return $this->redirectToRoute('admin_approvals', ['_fragment' => $kind]);
    }

    /**
     * Décision en lot. Deux usages : la sélection cochée (ids[]) ou tout le type (tout=1, y compris au-delà des 50 affichés).
     * Chaque élément passe par le même approve()/reject() que le bouton unitaire : aucune règle n'est contournée.
     */
    #[Route('/{kind}/lot/{decision<approuver|refuser>}', name: 'admin_approval_batch', methods: ['POST'])]
    public function batch(Request $r, ApprovalRegistry $registry, string $kind, string $decision): Response
    {
        $this->checkToken($r);
        $p = $registry->get($kind) ?? throw $this->createNotFoundException();
        $n = $this->apply($p, $decision, $r->request->getBoolean('tout') ? null : array_map('intval', (array) $r->request->all('ids')));
        $this->addFlash($n ? 'success' : 'warning', $n ? sprintf('%d élément(s) %s.', $n, 'approuver' === $decision ? 'approuvé(s)' : 'refusé(s)') : 'Rien à traiter : cochez au moins une ligne.');

        return $this->redirectToRoute('admin_approvals', ['_fragment' => $kind]);
    }

    /** Approuve absolument tout ce qui attend, tous types confondus. */
    #[Route('/tout', name: 'admin_approval_all', methods: ['POST'])]
    public function all(Request $r, ApprovalRegistry $registry): Response
    {
        $this->checkToken($r);
        $n = 0;
        foreach ($registry->all() as $p) {
            $n += $this->apply($p, 'approuver', null);
        }
        $this->addFlash($n ? 'success' : 'warning', $n ? sprintf('%d élément(s) approuvé(s).', $n) : 'Rien à valider.');

        return $this->redirectToRoute('admin_approvals');
    }

    /** @param list<int>|null $ids null = tout ce qui attend (plafonné à 500 par sécurité) */
    private function apply(\App\Service\Approval\ApprovalProvider $p, string $decision, ?array $ids): int
    {
        $n = 0;
        if (null === $ids) {
            $ids = array_map(static fn ($it) => $it->id, $p->pending(500));
        }
        foreach (array_unique($ids) as $id) {
            if ($id > 0 && ('approuver' === $decision ? $p->approve($id) : $p->reject($id))) {
                ++$n;
            }
        }

        return $n;
    }

    private function checkToken(Request $r): void
    {
        if (!$this->isCsrfTokenValid('approvals', (string) $r->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
