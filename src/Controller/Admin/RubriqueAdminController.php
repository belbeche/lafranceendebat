<?php

namespace App\Controller\Admin;

use App\Entity\{Dossier, Inspiration, MissionPost, Proposition, Debat, Tribune};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Gestion de chaque rubrique de contenu : liste filtrée, publication, retrait, refus motivé, correction et suppression. */
#[IsGranted('ROLE_MODERATOR')]
#[Route('/admin/rubriques')]
class RubriqueAdminController extends AbstractController
{
    private const KINDS = [
        'dossiers' => ['Dossiers de référence', Dossier::class, 'title', 'Dossier citoyen sourcé, utilisable dans les débats et propositions.'],
        'contributeurs' => ['Ils ont contribué', Inspiration::class, 'name', 'Fiches de personnes et de projets qui ont inspiré la plateforme.'],
        'annonces' => ['Annonces de mission', MissionPost::class, 'title', 'Besoins d\'aide et offres d\'aide entre citoyens, associations et élus.'],
        'tribunes' => ['Tribunes', Tribune::class, 'title', 'Textes d\'opinion argumentés et sourcés.'],
    ];
    private const STATUS = ['pending' => 'En attente', 'published' => 'Publié', 'rejected' => 'Refusé', 'closed' => 'Clos'];

    #[Route('', name: 'admin_rubriques', methods: ['GET'])]
    public function hub(EntityManagerInterface $em, \App\Service\MaintenanceMode $maintenance): Response
    {
        $rows = [];
        foreach (self::KINDS as $k => [$label, $class, , $desc]) {
            $c = ['pending' => 0, 'published' => 0, 'rejected' => 0];
            foreach ($em->createQuery("SELECT e.status s, COUNT(e.id) n FROM $class e GROUP BY e.status")->getArrayResult() as $r) {
                $c[$r['s']] = (int) $r['n'];
            }
            $rows[] = ['kind' => $k, 'label' => $label, 'desc' => $desc, 'count' => $c];
        }

        $overview = [];
        foreach ([
            ['Propositions', Proposition::class, 'admin_proposition_index', true], ['Débats', Debat::class, 'admin_debat_index', true],
            ['Arguments', \App\Entity\Argument::class, 'admin_moderation', true], ['Discussions', \App\Entity\Discussion::class, 'admin_moderation', true],
            ['Revendications', \App\Entity\Revendication::class, 'admin_revendication_index', true], ['Engagements', \App\Entity\Engagement::class, 'admin_engagement_index', true],
            ['Candidats', \App\Entity\Candidat::class, 'admin_candidat_index', false], ['Agenda', \App\Entity\Event::class, 'admin_event_index', false],
            ['Piliers d\'accueil', \App\Entity\Mission::class, 'admin_mission_index', false], ['Comptes', \App\Entity\User::class, 'admin_users', false],
            ['Signalements', \App\Entity\Signalement::class, 'admin_moderation', true],
            ['Affirmations de la page Positions', \App\Entity\PositionStatement::class, 'admin_positions', false], ['Positions de personnalités', \App\Entity\PositionStance::class, 'admin_positions', true],
            ['Votes de groupes (scrutins)', \App\Entity\GroupVote::class, 'admin_positions', false], ['Analyses sourcées', \App\Entity\Thesis::class, 'admin_theses', false],
            ['Mesures des groupes', \App\Entity\PartyProposal::class, 'admin_programmes', false], ['Soutiens à l\'initiative', \App\Entity\InitiativeSupport::class, 'admin_users', false],
        ] as [$name, $class, $route, $hasStatus]) {
            try {
                $total = (int) $em->createQuery("SELECT COUNT(e.id) FROM $class e")->getSingleScalarResult();
                $by = [];
                if ($hasStatus && $total) {
                    foreach ($em->createQuery("SELECT e.status s, COUNT(e.id) n FROM $class e GROUP BY e.status")->getArrayResult() as $r) {
                        $by[(string) $r['s']] = (int) $r['n'];
                    }
                }
                $overview[] = ['label' => $name, 'route' => $route, 'total' => $total, 'by' => $by];
            } catch (\Throwable) {
                $overview[] = ['label' => $name, 'route' => $route, 'total' => null, 'by' => []];
            }
        }
        $editorial = \App\Controller\DossierController::DOSSIERS;

        return $this->render('admin/rubriques/hub.html.twig', ['rows' => $rows, 'overview' => $overview, 'maintenance' => $maintenance->read(), 'editorialCount' => count($editorial) + count(\App\Service\DossierCatalog::STARTERS)]);
    }

    #[Route('/{kind}', name: 'admin_rubrique', requirements: ['kind' => 'dossiers|contributeurs|annonces|tribunes'], methods: ['GET'])]
    public function list(string $kind, Request $request, EntityManagerInterface $em): Response
    {
        [$label, $class, $titleField] = self::KINDS[$kind];
        $status = (string) $request->query->get('statut', '');
        $q = trim((string) $request->query->get('q', ''));
        $qb = $em->getRepository($class)->createQueryBuilder('e')->orderBy('e.id', 'DESC')->setMaxResults(200);
        if (isset(self::STATUS[$status])) {
            $qb->andWhere('e.status = :st')->setParameter('st', $status);
        }
        if ('' !== $q) {
            $qb->andWhere("e.$titleField LIKE :q")->setParameter('q', '%' . addcslashes(mb_substr($q, 0, 80), '%_') . '%');
        }

        $editorial = [];
        if ('dossiers' === $kind) {
            foreach (\App\Controller\DossierController::DOSSIERS as $slug => [$t, $i]) {
                $editorial[] = ['title' => $t, 'intro' => $i, 'url' => $this->generateUrl('app_dossier', ['slug' => $slug]), 'group' => 'Dossiers éditoriaux'];
            }
            foreach (\App\Service\DossierCatalog::STARTERS as $theme => $st) {
                $editorial[] = ['title' => $st[0], 'intro' => $st[1], 'url' => $this->generateUrl('app_dossier_page', ['slug' => \App\Service\DossierCatalog::starterSlug($theme)]), 'group' => 'Dossiers de départ par thème'];
            }
        }

        return $this->render('admin/rubriques/list.html.twig', ['editorial' => $editorial, 'kind' => $kind, 'label' => $label, 'rows' => $qb->getQuery()->getResult(), 'titleField' => $titleField, 'status' => $status, 'q' => $q, 'statuses' => self::STATUS]);
    }

    #[Route('/{kind}/{id}/{action}', name: 'admin_rubrique_action', requirements: ['kind' => 'dossiers|contributeurs|annonces|tribunes', 'id' => '\d+', 'action' => 'publier|retirer|refuser|supprimer'], methods: ['POST'])]
    public function act(string $kind, int $id, string $action, Request $request, EntityManagerInterface $em): Response
    {
        $back = $this->redirectToRoute('admin_rubrique', ['kind' => $kind, 'statut' => $request->request->get('statut', '')]);
        if (!$this->isCsrfTokenValid('rub' . $kind . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $back;
        }
        $e = $em->getRepository(self::KINDS[$kind][1])->find($id);
        if (!$e) {
            $this->addFlash('warning', 'Élément introuvable.');

            return $back;
        }
        if (in_array($action, ['publier', 'refuser'], true) && $e->getAuthor() === $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('warning', 'Vous ne pouvez pas traiter votre propre contenu.');

            return $back;
        }
        $note = trim((string) $request->request->get('note', ''));
        switch ($action) {
            case 'publier':
                $e->publish();
                $msg = 'Publié.';
                break;
            case 'retirer':
                $e->reopen();
                $msg = 'Retiré de la publication, remis en attente.';
                break;
            case 'refuser':
                if ('' === $note) {
                    $this->addFlash('warning', 'Un refus doit être motivé.');

                    return $back;
                }
                $e->reject($note);
                $msg = 'Refusé, motif enregistré.';
                break;
            default:
                if (!$this->isGranted('ROLE_ADMIN')) {
                    throw $this->createAccessDeniedException();
                }
                if ($e instanceof Dossier && $this->dossierUses($em, $e) > 0) {
                    $this->addFlash('warning', 'Ce dossier est utilisé par des débats ou propositions : retirez-le de la publication plutôt que le supprimer.');

                    return $back;
                }
                $em->remove($e);
                $msg = 'Supprimé définitivement.';
        }
        $em->flush();
        $this->addFlash('success', $msg);

        return $back;
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/contributeurs/charger', name: 'admin_rubrique_seed_contrib', methods: ['POST'], priority: 6)]
    public function seedContributors(Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('rubseed', (string) $request->request->get('_token'))) {
            $n = \App\Command\SeedInspirationsCommand::seed($em);
            $this->addFlash('success', $n . ' fiche(s) de départ ajoutée(s) et publiée(s).');
        } else {
            $this->addFlash('danger', 'Session expirée. Réessayez.');
        }

        return $this->redirectToRoute('admin_rubrique', ['kind' => 'contributeurs']);
    }

    #[Route('/contributeurs/{id}/avancees', name: 'admin_rubrique_inspiration_advances', requirements: ['id' => '\d+'], methods: ['GET', 'POST'], priority: 5)]
    public function editAdvances(Inspiration $i, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            if ($this->isCsrfTokenValid('adv' . $i->getId(), (string) $request->request->get('_token'))) {
                $i->setAdvances((string) $request->request->get('advances'));
                $em->flush();
                $this->addFlash('success', 'Avancées enregistrées.');

                return $this->redirectToRoute('admin_rubrique', ['kind' => 'contributeurs']);
            }
            $this->addFlash('danger', 'Session expirée.');
        }

        return $this->render('admin/rubriques/advances_edit.html.twig', ['i' => $i]);
    }

    #[Route('/dossiers/nouveau', name: 'admin_rubrique_dossier_new', methods: ['GET', 'POST'], priority: 10)]
    public function newDossier(Request $request, EntityManagerInterface $em): Response
    {
        return $this->dossierForm(new Dossier(), $request, $em, true);
    }

    #[Route('/dossiers/{id}/modifier', name: 'admin_rubrique_dossier_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'], priority: 5)]
    public function editDossier(Dossier $d, Request $request, EntityManagerInterface $em): Response
    {
        return $this->dossierForm($d, $request, $em, false);
    }

    /**
     * Création et modification complètes d'un dossier par l'administration : titre, adresse (slug), thème, résumé,
     * texte (mise en forme simple, voir SafeMarkdown), sources https, état (publié / en attente / refusé) et mention "rédaction".
     * Un dossier publié dont l'adresse est celle d'un dossier intégré (ex. histoire-guerres) remplace la page d'origine.
     */
    private function dossierForm(Dossier $d, Request $request, EntityManagerInterface $em, bool $isNew): Response
    {
        $errors = [];
        $tokenId = 'rubedit' . ($d->getId() ?? 'new');
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée. Réessayez.';
            }
            $title = trim((string) $request->request->get('title'));
            $intro = trim((string) $request->request->get('intro'));
            $body = trim((string) $request->request->get('body'));
            $theme = trim((string) $request->request->get('theme'));
            $slug = trim((string) $request->request->get('slug'));
            $state = (string) $request->request->get('state', $d->getStatus());
            $links = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $request->request->get('sources')) ?: [])));
            foreach ($links as $l) {
                if (!preg_match('#^https://\S+$#i', $l)) {
                    $errors[] = 'Chaque source doit être une adresse https : ' . mb_substr($l, 0, 60);
                    break;
                }
            }
            if (mb_strlen($title) < 5 || mb_strlen($title) > 160) { $errors[] = 'Titre : 5 à 160 caractères.'; }
            if ('' === $intro || mb_strlen($intro) > 400) { $errors[] = 'Introduction : 1 à 400 caractères.'; }
            if (mb_strlen($body) < 40) { $errors[] = 'Texte : 40 caractères minimum.'; }
            if (!$links) { $errors[] = 'Au moins une source https est requise.'; }
            if ('' === $slug) {
                $slug = (string) (new \Symfony\Component\String\Slugger\AsciiSlugger())->slug(mb_strtolower($title));
            }
            $slug = mb_substr(strtolower($slug), 0, 150);
            if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || 'proposer' === $slug || 'nouveau' === $slug) {
                $errors[] = 'Adresse : lettres minuscules, chiffres et tirets seulement.';
            } elseif (($other = $em->getRepository(Dossier::class)->findOneBy(['slug' => $slug])) && $other !== $d) {
                $errors[] = 'Cette adresse est déjà utilisée par un autre dossier.';
            } elseif (!$isNew && $slug !== $d->getSlug() && $this->dossierUses($em, $d) > 0) {
                $errors[] = 'Ce dossier est utilisé par des débats ou propositions : son adresse ne peut plus changer.';
            }
            if (!in_array($state, [Dossier::STATUS_PENDING, Dossier::STATUS_PUBLISHED, Dossier::STATUS_REJECTED], true)) {
                $errors[] = 'État inconnu.';
            }
            if ('' !== $theme && !in_array($theme, Proposition::THEMES, true)) {
                $errors[] = 'Thème inconnu.';
            }
            if (!$errors) {
                $d->setTitle($title)->setSlug($slug)->setIntro($intro)->setBody($body)->setTheme($theme ?: null)->setSources(implode("\n", $links))->setEditorial($request->request->getBoolean('editorial'));
                if ($isNew) {
                    $d->setAuthor($this->getUser() instanceof \App\Entity\User ? $this->getUser() : null);
                    $em->persist($d);
                }
                if (Dossier::STATUS_PUBLISHED === $state && !$d->isPublished()) {
                    $d->publish();
                } elseif (Dossier::STATUS_PENDING === $state && $d->isPublished()) {
                    $d->reopen();
                } elseif (Dossier::STATUS_REJECTED === $state && Dossier::STATUS_REJECTED !== $d->getStatus()) {
                    $d->reject((string) ($d->getModerationNote() ?: 'Retiré par l\'administration.'));
                }
                $em->flush();
                $this->addFlash('success', $isNew ? 'Dossier créé.' : 'Dossier enregistré.');

                return $this->redirectToRoute('admin_rubrique', ['kind' => 'dossiers']);
            }
        }

        return $this->render('admin/rubriques/dossier_edit.html.twig', ['d' => $d, 'errors' => $errors, 'themes' => Proposition::THEMES, 'isNew' => $isNew, 'tokenId' => $tokenId]);
    }

    private function dossierUses(EntityManagerInterface $em, Dossier $d): int
    {
        $n = 0;
        foreach ([Debat::class, Proposition::class] as $c) {
            $n += (int) $em->createQuery("SELECT COUNT(x.id) FROM $c x WHERE x.dossierSlug = :s")->setParameter('s', $d->getSlug())->getSingleScalarResult();
        }

        return $n;
    }
}
