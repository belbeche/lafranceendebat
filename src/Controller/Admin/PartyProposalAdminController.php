<?php

namespace App\Controller\Admin;

use App\Entity\PartyProposal;
use App\Entity\Proposition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration des propositions de groupes politiques.
 *
 *  - GET  /admin/programmes                 liste (filtres : groupe, sans chiffrage, mot-clé)
 *  - GET|POST /admin/programmes/nouvelle    ajouter une mesure de programme publiée par la formation (URL https obligatoire)
 *  - GET|POST /admin/programmes/{id}        corriger le thème, publier ou masquer, renseigner un chiffrage
 *
 * Règles d'équité : mêmes champs et mêmes exigences pour tous les groupes ; un chiffrage exige un niveau de
 * crédibilité ET une source https:// (sinon il est ignoré par l'entité) ; on ne modifie jamais le titre ni la
 * source d'une proposition importée (elle se resynchronise depuis les données officielles).
 */
#[Route('/admin/programmes')]
#[IsGranted('ROLE_ADMIN')]
class PartyProposalAdminController extends AbstractController
{
    #[Route('', name: 'admin_programmes', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $qb = $em->getRepository(PartyProposal::class)->createQueryBuilder('p');
        if ($g = (string) $request->query->get('groupe')) {
            $qb->andWhere('p.groupCode = :g')->setParameter('g', $g);
        }
        if ($request->query->getBoolean('sans_chiffrage')) {
            $qb->andWhere('p.costMillions IS NULL');
        }
        if ($q = trim((string) $request->query->get('q'))) {
            $qb->andWhere('p.title LIKE :q')->setParameter('q', '%' . addcslashes(mb_substr($q, 0, 80), '%_') . '%');
        }
        $total = (int) (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->orderBy('p.id', 'DESC')->setMaxResults(100)->getQuery()->getResult();
        $groups = $em->createQuery('SELECT p.groupCode AS code, COUNT(p.id) AS n FROM App\Entity\PartyProposal p GROUP BY p.groupCode ORDER BY p.groupCode')->getArrayResult();

        return $this->render('admin/programmes/index.html.twig', ['items' => $items, 'total' => $total, 'groups' => $groups, 'f' => $request->query->all()]);
    }

    #[Route('/nouvelle', name: 'admin_programme_new', methods: ['GET', 'POST'])]
    #[Route('/{id<\d+>}', name: 'admin_programme_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $em, ?PartyProposal $p = null): Response
    {
        $isNew = null === $p;
        $p ??= (new PartyProposal())->setSource(PartyProposal::SOURCE_MANUAL)->setExternalId('manuel-' . bin2hex(random_bytes(6)));
        $errors = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('programme_edit', (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée.';
            }
            $r = $request->request;
            if ($isNew) {
                $p->setGroup((string) $r->get('group_code'), (string) $r->get('group_name'), (string) $r->get('group_color') ?: null)
                    ->setTitle((string) $r->get('title'))->setSummary((string) $r->get('summary'))->setSourceUrl((string) $r->get('source_url'));
                if (mb_strlen($p->getGroupCode()) < 2 || mb_strlen($p->getGroupName()) < 3) {
                    $errors[] = 'Indiquez le sigle et le nom de la formation.';
                }
                if (mb_strlen($p->getTitle()) < 8) {
                    $errors[] = 'Le titre doit faire au moins 8 caractères.';
                }
                if (!$p->hasValidSource()) {
                    $errors[] = 'Une adresse https:// vers le site officiel de la formation est obligatoire : sans source, pas de publication.';
                }
            }
            $p->setTheme((string) $r->get('theme') ?: null)->setPublished($r->getBoolean('published'));
            $cost = trim((string) $r->get('cost_millions'));
            if ('' !== $cost && !preg_match('/^-?\d{1,7}$/', $cost)) {
                $errors[] = 'Le coût est un nombre entier de millions d\'euros par an (ex. 1200, ou -300 pour une économie).';
            } elseif ('' !== $cost) {
                $p->setCost((int) $cost, (string) $r->get('cost_credibility'), (string) $r->get('cost_source'), (string) $r->get('cost_note'));
                if (!$p->isCosted()) {
                    $errors[] = 'Un chiffrage exige un niveau de crédibilité (A, B ou C) et une source en https://. Il n\'a pas été enregistré.';
                }
            } else {
                $p->setCost(null, null, null);
            }
            if (!$errors) {
                $em->persist($p);
                $em->flush();
                $this->addFlash('success', 'Proposition enregistrée.');

                return $this->redirectToRoute('admin_programmes');
            }
        }

        return $this->render('admin/programmes/edit.html.twig', ['p' => $p, 'isNew' => $isNew, 'errors' => $errors, 'themes' => Proposition::THEMES]);
    }

    /** Lance l'analyse Gemini d'UNE proposition. Resultat : en attente de validation, invisible du public. */
    #[Route('/{id<\d+>}/ia/analyser', name: 'admin_programme_ai_run', methods: ['POST'])]
    public function aiRun(PartyProposal $p, Request $request, EntityManagerInterface $em, \App\Service\Programme\ProposalAnalyst $analyst): Response
    {
        $this->csrf($request, 'programme_ai' . $p->getId());
        if (!$analyst->isEnabled()) {
            $this->addFlash('error', 'GEMINI_API_KEY n\'est pas configurée : aucune analyse possible. Rien n\'est inventé.');
        } elseif (null === ($a = $analyst->analyse($p))) {
            $this->addFlash('error', 'Gemini n\'a pas fourni d\'analyse exploitable (indisponible ou réponse hors cadre). Réessayez plus tard.');
        } else {
            $p->setAiAnalysis($a, $analyst->getModel());
            $em->flush();
            $this->addFlash('success', $a['costable'] ? 'Hypothèse produite. Relisez-la avant de la valider : elle reste invisible du public.' : 'Gemini juge cette proposition non chiffrable : ' . ($a['reason'] ?: 'raison non précisée') . '.');
        }

        return $this->redirectToRoute('admin_programme_edit', ['id' => $p->getId()]);
    }

    #[Route('/{id<\d+>}/ia/valider', name: 'admin_programme_ai_approve', methods: ['POST'])]
    public function aiApprove(PartyProposal $p, Request $request, EntityManagerInterface $em): Response
    {
        $this->csrf($request, 'programme_ai' . $p->getId());
        $p->setAiApproved(true);
        $em->flush();
        $this->addFlash('success', $p->isAiPublic() ? 'Hypothèse IA publiée, étiquetée « hypothèse IA, non officielle ».' : 'Rien à valider.');

        return $this->redirectToRoute('admin_programme_edit', ['id' => $p->getId()]);
    }

    #[Route('/{id<\d+>}/ia/retirer', name: 'admin_programme_ai_remove', methods: ['POST'])]
    public function aiRemove(PartyProposal $p, Request $request, EntityManagerInterface $em): Response
    {
        $this->csrf($request, 'programme_ai' . $p->getId());
        $p->setAiAnalysis(null);
        $em->flush();
        $this->addFlash('success', 'Hypothèse IA retirée.');

        return $this->redirectToRoute('admin_programme_edit', ['id' => $p->getId()]);
    }

    private function csrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }
    }

    /** Liens officiels d'une formation (site, programme). Uniquement des adresses https fournies par la formation elle-meme. */
    #[Route('/partis/{code}', name: 'admin_programme_party', requirements: ['code' => '[A-Za-z0-9\-]{2,12}'], methods: ['GET', 'POST'])]
    public function party(string $code, Request $request, EntityManagerInterface $em): Response
    {
        $name = $em->createQuery('SELECT MAX(p.groupName) FROM App\Entity\PartyProposal p WHERE p.groupCode = :c')->setParameter('c', $code)->getSingleScalarResult();
        if (!$name) {
            throw $this->createNotFoundException();
        }
        $pf = $em->getRepository(\App\Entity\PartyProfile::class)->findOneBy(['groupCode' => $code]) ?? (new \App\Entity\PartyProfile())->setGroupCode($code);
        $errors = [];
        if ($request->isMethod('POST')) {
            $this->csrf($request, 'party_profile');
            $r = $request->request;
            $site = trim((string) $r->get('official_site'));
            $prog = trim((string) $r->get('programme_url'));
            $pf->setOfficialSite($site)->setProgrammeUrl($prog);
            if (('' !== $site && null === $pf->getOfficialSite()) || ('' !== $prog && null === $pf->getProgrammeUrl())) {
                $errors[] = 'Chaque lien doit être une adresse https:// valide, sans identifiant.';
            } else {
                $pf->setLinksCheckedAt($r->getBoolean('checked') ? new \DateTimeImmutable() : $pf->getLinksCheckedAt());
                $em->persist($pf);
                $em->flush();
                $this->addFlash('success', 'Liens officiels enregistrés.');

                return $this->redirectToRoute('admin_programmes');
            }
        }

        return $this->render('admin/programmes/party.html.twig', ['code' => $code, 'name' => $name, 'pf' => $pf, 'errors' => $errors]);
    }
}
