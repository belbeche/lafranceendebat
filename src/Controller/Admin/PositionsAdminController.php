<?php

namespace App\Controller\Admin;

use App\Entity\PositionStance;
use App\Entity\PositionStatement;
use App\Entity\GroupVote;
use App\Service\Positions\PositionResearchRunner;
use App\Service\Positions\ScrutinImporter;
use App\Service\Positions\StanceResearcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/positions')]
#[IsGranted('ROLE_ADMIN')]
class PositionsAdminController extends AbstractController
{
    #[Route('', name: 'admin_positions', methods: ['GET', 'POST'])]
    public function index(Request $r, EntityManagerInterface $em): Response
    {
        if ($r->isMethod('POST')) {
            $this->csrf($r);
            $text = trim((string) $r->request->get('text'));
            if (mb_strlen($text) >= 10) {
                $em->persist(new PositionStatement((string) $r->request->get('theme', 'Général'), $text));
                $em->flush();
                $this->addFlash('success', 'Affirmation ajoutée (non publiée).');
            } else {
                $this->addFlash('error', 'Affirmation trop courte.');
            }

            return $this->redirectToRoute('admin_positions');
        }

        return $this->render('admin/positions/index.html.twig', ['statements' => $em->getRepository(PositionStatement::class)->findBy([], ['id' => 'DESC'])]);
    }

    #[Route('/{id<\d+>}', name: 'admin_positions_show', methods: ['GET'])]
    public function show(PositionStatement $s, EntityManagerInterface $em, StanceResearcher $researcher): Response
    {
        return $this->render('admin/positions/show.html.twig', ['s' => $s, 'stances' => $em->getRepository(PositionStance::class)->findBy(['statement' => $s], ['status' => 'ASC', 'id' => 'ASC']), 'aiOn' => $researcher->isEnabled(), 'votes' => $em->getRepository(GroupVote::class)->findBy(['statement' => $s], ['scrutin' => 'DESC', 'groupCode' => 'ASC']), 'axes' => PositionStatement::VIABILITY_AXES]);
    }

    #[Route('/{id<\d+>}/rechercher', name: 'admin_positions_research', methods: ['POST'])]
    public function research(Request $r, PositionStatement $s, PositionResearchRunner $runner): Response
    {
        $this->csrf($r);
        $res = $runner->run($s, 6, 80);
        $this->addFlash($res['errors'] && !$res['added'] ? 'error' : 'success', sprintf('%d position(s) proposée(s), %d sans position publique trouvée, %d écartée(s) faute de source vérifiable, %d personne(s) restante(s).', $res['added'], $res['none'], count($res['errors']), $res['remaining']));

        return $this->redirectToRoute('admin_positions_show', ['id' => $s->getId()]);
    }

    /** Synthèse et faisabilité : texte libre de l'éditeur, une source https par axe (sans source, l'axe s'affiche quand même mais sans lien). */
    #[Route('/{id<\d+>}/fiche', name: 'admin_positions_fiche', methods: ['POST'])]
    public function fiche(Request $r, PositionStatement $s, EntityManagerInterface $em): Response
    {
        $this->csrf($r);
        $s->setSynthesis((string) $r->request->get('synthesis'));
        foreach (array_keys(PositionStatement::VIABILITY_AXES) as $axis) {
            $s->setViabilityAxis($axis, (string) $r->request->get('via_' . $axis), (string) $r->request->get('via_' . $axis . '_url'));
        }
        $em->flush();
        $this->addFlash('success', 'Fiche enregistrée.');

        return $this->redirectToRoute('admin_positions_show', ['id' => $s->getId()]);
    }

    /** Rattache des scrutins officiels (numéros séparés par des espaces ou virgules) et enregistre le vote des groupes. */
    #[Route('/{id<\d+>}/scrutins', name: 'admin_positions_scrutins', methods: ['POST'])]
    public function scrutins(Request $r, PositionStatement $s, ScrutinImporter $importer): Response
    {
        $this->csrf($r);
        preg_match_all('/\d{1,6}/', (string) $r->request->get('numeros'), $m);
        foreach (array_slice(array_unique($m[0]), 0, 5) as $n) {
            try {
                $this->addFlash('success', sprintf('Scrutin %d : %d groupes enregistrés.', (int) $n, $importer->import($s, (int) $n)));
            } catch (\Throwable $e) {
                $this->addFlash('error', sprintf('Scrutin %d : %s', (int) $n, $e->getMessage()));
            }
        }

        return $this->redirectToRoute('admin_positions_show', ['id' => $s->getId()]);
    }

    #[Route('/vote/{id<\d+>}/supprimer', name: 'admin_positions_vote_delete', methods: ['POST'])]
    public function deleteVote(Request $r, GroupVote $v, EntityManagerInterface $em): Response
    {
        $this->csrf($r);
        $sid = $v->getStatement()->getId();
        $em->remove($v);
        $em->flush();

        return $this->redirectToRoute('admin_positions_show', ['id' => $sid]);
    }

    #[Route('/{id<\d+>}/publier', name: 'admin_positions_publish', methods: ['POST'])]
    public function publish(Request $r, PositionStatement $s, EntityManagerInterface $em): Response
    {
        $this->csrf($r);
        $s->setPublished(!$s->isPublished());
        $em->flush();

        return $this->redirectToRoute('admin_positions_show', ['id' => $s->getId()]);
    }

    #[Route('/position/{id<\d+>}/{action<valider|rejeter|supprimer>}', name: 'admin_positions_stance', methods: ['POST'])]
    public function stance(Request $r, PositionStance $x, string $action, EntityManagerInterface $em): Response
    {
        $this->csrf($r);
        $sid = $x->getStatement()->getId();
        if ('supprimer' === $action) {
            $em->remove($x);
        } else {
            $x->setStatus('valider' === $action ? PositionStance::APPROVED : PositionStance::REJECTED);
        }
        $em->flush();

        return $this->redirectToRoute('admin_positions_show', ['id' => $sid]);
    }

    private function csrf(Request $r): void
    {
        if (!$this->isCsrfTokenValid('positions_admin', (string) $r->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
