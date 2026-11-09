<?php

namespace App\Controller;

use App\Entity\PartyProposal;
use App\Entity\Proposition;
use App\Entity\ProposalOpinion;
use App\Entity\User;
use App\Service\ContentModerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Propositions des groupes politiques et calculateur de budget.
 *
 *  - GET  /programmes                       (app_programmes)        liste filtrable par groupe, thème et mot-clé
 *  - GET  /programmes/{id}                  (app_programme_show)    fiche : source officielle, chiffrage et crédibilité, avis des membres
 *  - POST /programmes/{id}/avis             (app_programme_opinion) avis consultatif (connexion requise, charte de la paix)
 *  - GET  /calculateur-budget               (app_budget_calculator) somme de propositions chiffrées, avec niveau de crédibilité
 *
 * Neutralité : tous les groupes sont traités par les mêmes règles, la même mise en page et les mêmes filtres ;
 * aucun classement, aucune note, aucun commentaire éditorial sur les groupes.
 */
class ProgrammeController extends AbstractController
{
    private const PER_PAGE = 20;

    #[Route('/programmes', name: 'app_programmes', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $group = (string) $request->query->get('groupe', '');
        $theme = (string) $request->query->get('theme', '');
        $q = trim((string) $request->query->get('q', ''));
        $costed = $request->query->getBoolean('chiffrees');
        $page = max(1, $request->query->getInt('page', 1));
        $qb = $em->getRepository(PartyProposal::class)->createQueryBuilder('p')->andWhere('p.published = true');
        if ('' !== $group) {
            $qb->andWhere('p.groupCode = :g')->setParameter('g', $group);
        }
        if (in_array($theme, Proposition::THEMES, true)) {
            $qb->andWhere('p.theme = :t')->setParameter('t', $theme);
        }
        if ('' !== $q) {
            $qb->andWhere('p.title LIKE :q OR p.summary LIKE :q')->setParameter('q', '%' . addcslashes(mb_substr($q, 0, 80), '%_') . '%');
        }
        if ($costed) {
            $qb->andWhere('p.costMillions IS NOT NULL');
        }
        $total = (int) (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->orderBy('p.depositedAt', 'DESC')->addOrderBy('p.id', 'DESC')->setFirstResult(($page - 1) * self::PER_PAGE)->setMaxResults(self::PER_PAGE)->getQuery()->getResult();
        $groups = $em->createQuery('SELECT p.groupCode AS code, MAX(p.groupName) AS name, COUNT(p.id) AS n FROM App\Entity\PartyProposal p WHERE p.published = true GROUP BY p.groupCode ORDER BY p.groupCode ASC')->getArrayResult();

        return $this->render('programme/index.html.twig', [
            'items' => $items, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'groups' => $groups, 'themes' => Proposition::THEMES, 'f' => ['groupe' => $group, 'theme' => $theme, 'q' => $q, 'chiffrees' => $costed],
        ]);
    }

    #[Route('/programmes/{id<\d+>}', name: 'app_programme_show', methods: ['GET'])]
    public function show(PartyProposal $p, EntityManagerInterface $em): Response
    {
        if (!$p->isPublished()) {
            throw $this->createNotFoundException();
        }
        $repo = $em->getRepository(ProposalOpinion::class);
        $counts = ['pour' => 0, 'reserve' => 0, 'contre' => 0];
        foreach ($em->createQuery('SELECT o.stance AS s, COUNT(o.id) AS n FROM App\Entity\ProposalOpinion o WHERE o.proposal = :p GROUP BY o.stance')->setParameter('p', $p)->getArrayResult() as $r) {
            $counts[$r['s']] = (int) $r['n'];
        }
        $mine = $this->getUser() instanceof User ? $repo->findOneBy(['user' => $this->getUser(), 'proposal' => $p]) : null;
        $comments = $repo->findBy(['proposal' => $p], ['id' => 'DESC'], 15);

        return $this->render('programme/show.html.twig', ['p' => $p, 'counts' => $counts, 'mine' => $mine, 'comments' => array_filter($comments, static fn (ProposalOpinion $o) => null !== $o->getComment())]);
    }

    #[Route('/programmes/{id<\d+>}/avis', name: 'app_programme_opinion', methods: ['POST'])]
    public function opinion(PartyProposal $p, Request $request, EntityManagerInterface $em, ContentModerator $moderator): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if (!$p->isPublished() || !$this->isCsrfTokenValid('opinion' . $p->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('app_programme_show', ['id' => $p->getId()]);
        }
        if (!$request->request->getBoolean('charte')) {
            $this->addFlash('danger', 'Cochez l\'engagement de respecter la charte de la paix pour donner votre avis.');

            return $this->redirectToRoute('app_programme_show', ['id' => $p->getId()]);
        }
        $comment = trim((string) $request->request->get('comment'));
        if ('' !== $comment && [] !== $moderator->analyze($comment)) {
            $this->addFlash('danger', 'Votre commentaire contient un terme ou une donnée personnelle non autorisés par la charte. Reformulez calmement, en parlant de la mesure et non des personnes.');

            return $this->redirectToRoute('app_programme_show', ['id' => $p->getId()]);
        }
        $o = $em->getRepository(ProposalOpinion::class)->findOneBy(['user' => $user, 'proposal' => $p]) ?? new ProposalOpinion($user, $p);
        $o->setStance((string) $request->request->get('stance'))->setComment($comment);
        $em->persist($o);
        $em->flush();
        $this->addFlash('success', 'Votre avis est enregistré. Vous pouvez le modifier à tout moment.');

        return $this->redirectToRoute('app_programme_show', ['id' => $p->getId()]);
    }

    #[Route('/calculateur-budget', name: 'app_budget_calculator', methods: ['GET'])]
    public function calculator(Request $request, EntityManagerInterface $em): Response
    {
        $group = (string) $request->query->get('groupe', '');
        $qb = $em->getRepository(PartyProposal::class)->createQueryBuilder('p')->andWhere('p.published = true')->andWhere('p.costMillions IS NOT NULL OR (p.aiApproved = true AND p.aiCentralMillions IS NOT NULL)');
        if ('' !== $group) {
            $qb->andWhere('p.groupCode = :g')->setParameter('g', $group);
        }
        $available = $qb->orderBy('p.groupCode', 'ASC')->addOrderBy('p.title', 'ASC')->setMaxResults(300)->getQuery()->getResult();
        $ids = array_values(array_unique(array_map('intval', (array) $request->query->all('ids'))));
        $picked = $ids ? $em->getRepository(PartyProposal::class)->findBy(['id' => array_slice($ids, 0, 100), 'published' => true]) : [];
        $picked = array_values(array_filter($picked, static fn (PartyProposal $p) => $p->isCosted() || ($p->isAiPublic() && $p->hasAiEstimate())));
        $total = 0;
        $byLevel = ['A' => 0, 'B' => 0, 'C' => 0];
        // Hypotheses IA : TOTAL SEPARE, jamais ajoute aux niveaux A, B, C. Une proposition au chiffrage source n'entre pas ici.
        $ai = ['n' => 0, 'low' => 0, 'central' => 0, 'high' => 0];
        $includeAi = $request->query->getBoolean('ia');
        foreach ($picked as $p) {
            if ($p->isCosted()) {
                $total += (int) $p->getCostMillions();
                $byLevel[(string) $p->getCostCredibility()] += (int) $p->getCostMillions();
            } elseif ($includeAi) {
                ++$ai['n'];
                $ai['low'] += (int) $p->getAiLowMillions();
                $ai['central'] += (int) $p->getAiCentralMillions();
                $ai['high'] += (int) $p->getAiHighMillions();
            }
        }
        $groups = $em->createQuery('SELECT DISTINCT p.groupCode AS code FROM App\Entity\PartyProposal p WHERE p.published = true AND (p.costMillions IS NOT NULL OR (p.aiApproved = true AND p.aiCentralMillions IS NOT NULL)) ORDER BY p.groupCode ASC')->getArrayResult();

        return $this->render('programme/calculator.html.twig', ['available' => $available, 'picked' => $picked, 'ids' => array_map(static fn (PartyProposal $p) => $p->getId(), $picked), 'total' => $total, 'byLevel' => $byLevel, 'groups' => $groups, 'group' => $group, 'ai' => $ai, 'includeAi' => $includeAi]);
    }
}
