<?php

namespace App\Controller;

use App\Entity\Argument;
use App\Entity\Debat;
use App\Entity\User;
use App\Form\ArgumentType;
use App\Repository\ArgumentRepository;
use App\Repository\DebatRepository;
use App\Service\ContentModerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur HTTP « DebatController ».
 *
 * Routes :
 *  - GET /debats (app_debats)
 *  - GET,POST /debats/ouvrir (app_debat_new)
 *  - GET /debats/ouvrir/verifier (app_debat_check)
 *  - GET,POST /debats/{slug}/gerer (app_debat_manage)
 *  - GET /debats/{slug} (app_debat_show)
 *  - GET,POST /debats/{slug}/arguments/{id}/modifier (app_debat_argument_edit)
 *  - POST /debats/{slug}/arguments/{id}/retirer (app_debat_argument_withdraw)
 *  - POST /debats/{slug}/arguments (app_debat_argument_new)
 * Accès : ROLE_USER.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 * Tout texte public passe par ContentModerator avant d'être enregistré.
 */
#[Route('/debats')]
class DebatController extends AbstractController
{
    private array $obligations = [];

    public function __construct(private readonly \App\Service\Audience\ViewTracker $viewTracker)
    {
    }

    #[Route('', name: 'app_debats', methods: ['GET'])]
    public function index(Request $request, DebatRepository $debats, \App\Service\Algo\TrendingService $trending, \App\Service\Audience\ViewTracker $views): Response
    {
        $theme = (string) $request->query->get('theme', '');
        $custom = $debats->customThemes();
        $theme = in_array($theme, [...\App\Entity\Proposition::THEMES, ...$custom], true) ? $theme : null;
        $tri = in_array($request->query->get('tri'), ['recent', 'lus'], true) ? (string) $request->query->get('tri') : 'tendance';

        $list = $debats->findPublic($theme);
        $ids = array_map(static fn (Debat $d) => (int) $d->getId(), $list);
        $stats = $views->stats(\App\Entity\ContentView::DEBAT, $ids);
        if ('tendance' === $tri) {
            $list = array_map(static fn (array $r) => $r['debat'], $trending->rank($list));
        } elseif ('lus' === $tri) {
            usort($list, static fn (Debat $a, Debat $b) => ($stats[$b->getId()]['views'] ?? 0) <=> ($stats[$a->getId()]['views'] ?? 0));
        }

        return $this->render('debat/index.html.twig', [
            'debats' => $list,
            'stats' => $stats,
            'tri' => $tri,
            'themes' => [...\App\Entity\Proposition::THEMES, ...$custom],
            'theme' => $theme,
        ]);
    }

    /** Ouvrir un débat : exposé neutre et faits sourcés, relus avant publication. */
    #[Route('/ouvrir', name: 'app_debat_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function open(\App\Service\DossierCatalog $dossiers, Request $request, EntityManagerInterface $em, ContentModerator $moderator, \Symfony\Component\String\Slugger\SluggerInterface $slugger, \Symfony\Component\RateLimiter\RateLimiterFactory $debateUserLimiter, \App\Service\NotificationMailer $notifier): Response
    {
        $d = ['title' => '', 'theme' => '', 'summary' => '', 'facts' => '', 'authority' => '', 'dossier' => '', 'theme_other' => '', 'dossier_title' => ''];
        $errors = [];
        if ($request->isMethod('POST')) {
            foreach ($d as $k => $_) {
                $d[$k] = trim((string) $request->request->get($k, ''));
            }
            if (!$this->isCsrfTokenValid('debat_new', (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée, réessayez.';
            }
            if (mb_strlen($d['title']) < 10 || mb_strlen($d['title']) > 180) {
                $errors[] = 'Le titre doit faire entre 10 et 180 caractères, sous forme de question neutre si possible.';
            }
            $theme = $this->resolveTheme($d['theme'], $d['theme_other'], $moderator, $errors);
            $newDossier = '__nouveau' === $d['dossier'];
            if ($newDossier) {
                if (mb_strlen($d['dossier_title']) < 8 || mb_strlen($d['dossier_title']) > 160) {
                    $errors[] = 'Le titre du nouveau dossier doit faire entre 8 et 160 caractères.';
                } elseif ($moderator->analyze($d['dossier_title']) !== []) {
                    $errors[] = 'Le titre du dossier contient un terme non autorisé.';
                }
                if ($em->getRepository(\App\Entity\Dossier::class)->count(['author' => $this->getUser(), 'status' => \App\Entity\Dossier::STATUS_PENDING]) >= 3) {
                    $errors[] = 'Vous avez déjà 3 dossiers en attente de validation. Choisissez-en un existant.';
                }
            } elseif (!$dossiers->isValidChoice($d['dossier'], $this->getUser())) {
                $errors[] = 'Choisissez le dossier auquel ce débat se rattache, ou proposez un nouveau dossier.';
            }
            if (mb_strlen($d['summary']) < 100 || mb_strlen($d['summary']) > 6000) {
                $errors[] = 'L\'exposé neutre doit faire entre 100 et 6000 caractères : décrivez le problème sans prendre parti.';
            }
            if (mb_strlen($d['facts']) < 40 || !preg_match('#https://[^\s]+\.[^\s]+#i', $d['facts'])) {
                $errors[] = 'Indiquez les faits vérifiés avec au moins une source publique (lien https).';
            }
            if ($moderator->analyze($d['title'] . "\n" . $d['summary'] . "\n" . $d['facts']) !== []) {
                $errors[] = 'Le texte contient une insulte, une menace ou une donnée personnelle. Reformulez calmement.';
            }
            if (!$errors && !$debateUserLimiter->create('u' . $this->getUser()->getId())->consume()->isAccepted()) {
                $errors[] = 'Limite atteinte : 3 débats par jour.';
            }
            if (!$errors) {
                $base = mb_substr(strtolower((string) $slugger->slug($d['title'])), 0, 170) ?: 'debat';
                $slug = $base;
                $n = 1;
                while ($em->getRepository(Debat::class)->findOneBy(['slug' => $slug])) {
                    $slug = $base . '-' . (++$n);
                }
                if ($newDossier) {
                    $dslug = mb_substr(strtolower((string) $slugger->slug($d['dossier_title'])), 0, 150) ?: 'dossier';
                    $dbase = $dslug;
                    $k = 1;
                    while ($em->getRepository(\App\Entity\Dossier::class)->findOneBy(['slug' => $dslug]) || isset(DossierController::DOSSIERS[$dslug]) || str_starts_with($dslug, 'theme-') || 'proposer' === $dslug) {
                        $dslug = $dbase . '-' . (++$k);
                    }
                    preg_match_all('#https://[^\s]+\.[^\s]+#i', $d['facts'], $mm);
                    $body = $d['summary'] . "\n\nFaits et sources :\n" . $d['facts'];
                    $em->persist((new \App\Entity\Dossier())->setAuthor($this->getUser())->setTitle($d['dossier_title'])->setSlug($dslug)->setTheme(in_array($theme, \App\Entity\Proposition::THEMES, true) ? $theme : null)
                        ->setIntro(mb_substr($d['summary'], 0, 397) . (mb_strlen($d['summary']) > 397 ? '…' : ''))->setBody($body)->setSources(implode("\n", array_unique($mm[0]))));
                    $d['dossier'] = $dslug;
                }
                $debat = (new Debat())->setTitle($d['title'])->setSlug($slug)->setTheme($theme)->setSummary($d['summary'])->setFacts($d['facts'])
                    ->setCompetentAuthority($d['authority'] ?: null)->setDossierSlug($d['dossier'])->setStatus(Debat::STATUS_DRAFT)->setProposedBy($this->getUser());
                $em->persist($debat);
                $em->flush();
                $notifier->debateSubmitted($debat);
                $this->addFlash('success', 'Merci. Votre débat est en relecture : il sera ouvert si l\'exposé est neutre et les faits sourcés.');

                return $this->redirectToRoute('app_my_requests');
            }
        }

        $dossierOptions = $dossiers->options($this->getUser());

        return $this->render('debat/new.html.twig', ['d' => $d, 'errors' => $errors, 'themes' => \App\Entity\Proposition::THEMES, 'dossiers' => $dossierOptions, 'themeDossier' => array_filter(array_combine(\App\Entity\Proposition::THEMES, array_map(static fn ($t) => isset(\App\Service\DossierCatalog::STARTERS[$t]) ? \App\Service\DossierCatalog::starterSlug($t) : '', \App\Entity\Proposition::THEMES)), static fn ($v) => '' !== $v && isset($dossierOptions[$v])), 'themeSources' => array_combine(\App\Entity\Proposition::THEMES, array_map([\App\Service\DebatObligations::class, 'sourcesFor'], \App\Entity\Proposition::THEMES)), 'checkUrl' => $this->generateUrl('app_debat_check')]);
    }

    /** Verification en direct des obligations liees aux sources officielles (formulaire d'ouverture). */
    #[Route('/ouvrir/verifier', name: 'app_debat_check', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function check(Request $request, \App\Service\DebatObligations $obligations): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $q = static fn (string $k): string => mb_substr((string) $request->query->get($k, ''), 0, 6000);
        $theme = $q('theme');
        $items = $obligations->evaluate($q('title'), $theme, $q('summary'), $q('facts'), $q('authority'));

        return $this->json(['items' => $items, 'ready' => \App\Service\DebatObligations::ready($items), 'sources' => \App\Service\DebatObligations::sourcesFor($theme)]);
    }

    #[Route('/{slug}/gerer', name: 'app_debat_manage', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function manage(string $slug, Request $request, DebatRepository $debats, EntityManagerInterface $em, ContentModerator $moderator, \App\Service\RevisionRecorder $revisions): Response
    {
        $debat = $debats->findOneBy(['slug' => $slug]) ?? throw $this->createNotFoundException();
        $isModerator = $this->isGranted('ROLE_MODERATOR');
        $isAuthor = $debat->getProposedBy() !== null && $debat->getProposedBy()->getId() === $this->getUser()->getId();
        if (!$isModerator && !$isAuthor) {
            throw $this->createAccessDeniedException();
        }
        $d = [
            'title' => (string) $debat->getTitle(), 'theme' => (string) $debat->getTheme(), 'summary' => (string) $debat->getSummary(),
            'facts' => (string) $debat->getFacts(), 'authority' => (string) $debat->getCompetentAuthority(),
            'status' => $debat->getStatus(), 'closesAt' => $debat->getClosesAt()?->format('Y-m-d') ?? '', 'outcome' => (string) $debat->getOutcome(),
        ];
        $errors = [];
        if ($request->isMethod('POST')) {
            foreach ($d as $k => $_) {
                $d[$k] = trim((string) $request->request->get($k, $d[$k]));
            }
            if (!$this->isCsrfTokenValid('debat_manage' . $debat->getId(), (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée, réessayez.';
            }
            if (mb_strlen($d['title']) < 10 || mb_strlen($d['title']) > 180) {
                $errors[] = 'Le titre doit faire entre 10 et 180 caractères.';
            }
            $d['theme'] = (string) $this->resolveTheme($d['theme'], (string) $request->request->get('theme_other', ''), $moderator, $errors);
            if (mb_strlen($d['summary']) < 100 || mb_strlen($d['summary']) > 6000) {
                $errors[] = 'L\'exposé doit faire entre 100 et 6000 caractères.';
            }
            if (mb_strlen($d['facts']) < 40 || !preg_match('#https://[^\s]+\.[^\s]+#i', $d['facts'])) {
                $errors[] = 'Indiquez les faits vérifiés avec au moins une source publique (lien https).';
            }
            if (mb_strlen($d['outcome']) > 6000) {
                $errors[] = 'Le compte rendu ne doit pas dépasser 6000 caractères.';
            }
            if ($moderator->analyze($d['title'] . "\n" . $d['summary'] . "\n" . $d['facts'] . "\n" . $d['outcome']) !== []) {
                $errors[] = 'Le texte contient une insulte, une menace ou une donnée personnelle. Reformulez calmement.';
            }
            $closes = null;
            if ($isModerator) {
                if (!isset(Debat::STATUS_LABELS[$d['status']])) {
                    $errors[] = 'Statut invalide.';
                }
                if ($d['closesAt'] !== '') {
                    $closes = \DateTimeImmutable::createFromFormat('!Y-m-d', $d['closesAt']) ?: null;
                    if (!$closes) {
                        $errors[] = 'Date de clôture invalide.';
                    }
                }
            }
            if (!$errors) {
                $revisions->record('debat', (int) $debat->getId(), ['title' => (string) $debat->getTitle(), 'theme' => (string) $debat->getTheme(), 'summary' => (string) $debat->getSummary(), 'facts' => (string) $debat->getFacts(), 'authority' => (string) $debat->getCompetentAuthority(), 'outcome' => (string) $debat->getOutcome()], $debat->getCreatedAt(), $isModerator && !$isAuthor, ['title' => $d['title'], 'theme' => $d['theme'], 'summary' => $d['summary'], 'facts' => $d['facts'], 'authority' => $d['authority'], 'outcome' => $isModerator ? $d['outcome'] : (string) $debat->getOutcome()]);
                $debat->setTitle($d['title'])->setTheme($d['theme'])->setSummary($d['summary'])->setFacts($d['facts'])
                    ->setCompetentAuthority($d['authority'] ?: null);
                if ($isModerator) {
                    $debat->setStatus($d['status'])->setClosesAt($closes)->setOutcome($d['outcome'] ?: null);
                }
                if ($debat->getStatus() !== Debat::STATUS_DRAFT) {
                    $debat->touch();
                }
                $em->flush();
                $this->addFlash('success', 'Débat mis à jour. L\'adresse de la page reste inchangée.');

                return $this->redirectToRoute($debat->getStatus() === Debat::STATUS_DRAFT ? 'app_my_requests' : 'app_debat_show', $debat->getStatus() === Debat::STATUS_DRAFT ? [] : ['slug' => $debat->getSlug()]);
            }
        }

        return $this->render('debat/manage.html.twig', ['d' => $d, 'errors' => $errors, 'debat' => $debat, 'themes' => \App\Entity\Proposition::THEMES, 'isModerator' => $isModerator, 'statuses' => Debat::STATUS_LABELS]);
    }

    #[Route('/{slug}', name: 'app_debat_show', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(string $slug, Request $request, DebatRepository $debats, ArgumentRepository $arguments, \App\Service\Audience\ViewTracker $views, \App\Service\DebatObligations $obligations): Response
    {
        $debat = $debats->findPublicBySlug($slug) ?? throw $this->createNotFoundException();
        $this->obligations = $obligations->evaluate((string) $debat->getTitle(), $debat->getTheme(), (string) $debat->getSummary(), (string) $debat->getFacts(), (string) $debat->getCompetentAuthority());
        $views->recordView(\App\Entity\ContentView::DEBAT, (int) $debat->getId(), $request);

        return $this->renderShow($debat, $request, $arguments, $this->buildForm($request, $arguments, $debat)['form']);
    }

    #[Route('/{slug}/arguments/{id}/modifier', name: 'app_debat_argument_edit', requirements: ['slug' => '[a-z0-9-]+', 'id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function editArgument(string $slug, int $id, Request $request, DebatRepository $debats, ArgumentRepository $arguments, EntityManagerInterface $em, ContentModerator $moderator, \App\Service\RevisionRecorder $revisions): Response
    {
        $debat = $debats->findPublicBySlug($slug) ?? throw $this->createNotFoundException();
        $argument = $arguments->find($id);
        if (!$argument || $argument->getDebat()?->getId() !== $debat->getId() || !$argument->isVisible()) {
            throw $this->createNotFoundException();
        }
        $mod = $this->isGranted('ROLE_MODERATOR');
        $mine = $argument->getAuthor() !== null && $argument->getAuthor()->getId() === $this->getUser()->getId();
        if (!$mod && !($mine && $debat->isOpen())) {
            $this->addFlash('warning', $mine ? 'Ce débat est clos : les arguments ne peuvent plus être modifiés.' : 'Vous ne pouvez modifier que vos propres arguments.');

            return $this->redirectToRoute('app_debat_show', ['slug' => $slug]);
        }
        $d = ['content' => (string) $argument->getContent(), 'sourceUrl' => (string) $argument->getSourceUrl(), 'reformulation' => (string) $argument->getReformulation()];
        $errors = [];
        if ($request->isMethod('POST')) {
            foreach ($d as $k => $_) {
                $d[$k] = trim((string) $request->request->get($k, ''));
            }
            if (!$this->isCsrfTokenValid('arg_edit' . $argument->getId(), (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée, réessayez.';
            }
            if (mb_strlen($d['content']) < 20 || mb_strlen($d['content']) > 6000) {
                $errors[] = 'Votre argument doit faire entre 20 et 6000 caractères.';
            }
            if ($d['sourceUrl'] !== '' && (!preg_match('#^https://[^\s]+\.[^\s]+$#i', $d['sourceUrl']) || mb_strlen($d['sourceUrl']) > 255)) {
                $errors[] = 'La source doit être un lien https valide.';
            }
            if (mb_strlen($d['reformulation']) > 1000) {
                $errors[] = 'La reformulation ne doit pas dépasser 1000 caractères.';
            }
            if ($argument->getParent() && $d['reformulation'] === '') {
                $errors[] = 'Reformulez d\'abord la position à laquelle vous répondez.';
            }
            if ($moderator->analyze($d['content'] . "\n" . $d['reformulation']) !== []) {
                $errors[] = 'Le texte contient une insulte, une menace ou une donnée personnelle. Reformulez calmement.';
            }
            if (!$errors) {
                $revisions->record('argument', (int) $argument->getId(), ['content' => (string) $argument->getContent(), 'sourceUrl' => (string) $argument->getSourceUrl(), 'reformulation' => (string) $argument->getReformulation()], $argument->getCreatedAt(), $mod && !$mine, ['content' => $d['content'], 'sourceUrl' => $d['sourceUrl'], 'reformulation' => $d['reformulation']]);
                $argument->setContent($d['content'])->setSourceUrl($d['sourceUrl'] ?: null)->setReformulation($d['reformulation'] ?: null)->markEdited();
                $em->flush();
                $this->addFlash('success', 'Argument mis à jour.' . ($argument->getUpdatedAt() ? ' La page indique qu\'il a été modifié.' : ''));

                return $this->redirectToRoute('app_debat_show', ['slug' => $slug, '_fragment' => 'argument-' . $argument->getId()]);
            }
        }

        return $this->render('debat/argument_edit.html.twig', ['debat' => $debat, 'argument' => $argument, 'd' => $d, 'errors' => $errors]);
    }

    #[Route('/{slug}/arguments/{id}/retirer', name: 'app_debat_argument_withdraw', requirements: ['slug' => '[a-z0-9-]+', 'id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function withdrawArgument(string $slug, int $id, Request $request, DebatRepository $debats, ArgumentRepository $arguments, EntityManagerInterface $em): Response
    {
        $debat = $debats->findPublicBySlug($slug) ?? throw $this->createNotFoundException();
        $argument = $arguments->find($id);
        if (!$argument || $argument->getDebat()?->getId() !== $debat->getId() || !$argument->isVisible()) {
            throw $this->createNotFoundException();
        }
        $mine = $argument->getAuthor() !== null && $argument->getAuthor()->getId() === $this->getUser()->getId();
        if (!$mine) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('arg_withdraw' . $argument->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée, réessayez.');

            return $this->redirectToRoute('app_debat_show', ['slug' => $slug]);
        }
        $argument->withdraw();
        $em->flush();
        $this->addFlash('success', 'Votre argument est retiré du débat. Les modérateurs gardent une trace en cas de litige.');

        return $this->redirectToRoute('app_debat_show', ['slug' => $slug]);
    }

    #[Route('/{slug}/arguments', name: 'app_debat_argument_new', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function newArgument(
        string $slug,
        Request $request,
        DebatRepository $debats,
        ArgumentRepository $arguments,
        EntityManagerInterface $em,
        ContentModerator $moderator,
        \App\Service\NotificationMailer $notifier,
    ): Response {
        $debat = $debats->findPublicBySlug($slug) ?? throw $this->createNotFoundException();
        if (!$debat->isOpen()) {
            $this->addFlash('warning', 'Ce débat est clos : il n\'accepte plus de nouvelles contributions.');

            return $this->redirectToRoute('app_debat_show', ['slug' => $slug]);
        }

        ['form' => $form, 'argument' => $argument, 'parent' => $parent] = $this->buildForm($request, $arguments, $debat);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reasons = $moderator->analyze($argument->getContent() . "\n" . $argument->getReformulation());
            if ($reasons !== []) {
                $this->addFlash('danger', $moderator->explain($reasons));

                return $this->renderShow($debat, $request, $arguments, $form, Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            /** @var User $user */
            $user = $this->getUser();
            if (!$user->hasAcceptedCharter()) {
                $user->setCharterAcceptedAt(new \DateTimeImmutable());
            }
            // Le filtre a priori est passe : la contribution est publiee. Les signalements prennent le relais.
            $argument->setStatus(Argument::STATUS_VALIDATED);
            $em->persist($argument);
            $em->flush();
            $notifier->argumentPosted($argument);

            $this->addFlash('success', $parent ? 'Votre réponse est publiée.' : 'Votre contribution est publiée. Merci de faire avancer le débat.');

            return $this->redirectToRoute('app_debat_show', ['slug' => $slug, '_fragment' => 'argument-' . $argument->getId()]);
        }

        return $this->renderShow($debat, $request, $arguments, $form, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @return array{form: \Symfony\Component\Form\FormInterface, argument: Argument, parent: ?Argument} */
    private function buildForm(Request $request, ArgumentRepository $arguments, Debat $debat): array
    {
        $parent = null;
        $parentId = $request->query->getInt('repondre');
        if ($parentId > 0) {
            $candidate = $arguments->find($parentId);
            if ($candidate && $candidate->getDebat()?->getId() === $debat->getId() && $candidate->isVisible() && $candidate->getParent() === null) {
                $parent = $candidate;
            }
        }

        $argument = new Argument();
        $argument->setDebat($debat)->setAuthor($this->getUser() instanceof User ? $this->getUser() : null)->setParent($parent);

        $action = $this->generateUrl('app_debat_argument_new', array_filter(['slug' => $debat->getSlug(), 'repondre' => $parent?->getId()]));
        $form = $this->createForm(ArgumentType::class, $argument, ['is_reply' => $parent !== null, 'action' => $action]);

        return ['form' => $form, 'argument' => $argument, 'parent' => $parent];
    }

    private function renderShow(Debat $debat, Request $request, ArgumentRepository $arguments, $form, int $status = Response::HTTP_OK): Response
    {
        return $this->render('debat/show.html.twig', [
            'debat' => $debat,
            'viewStats' => $this->viewTracker->stats(\App\Entity\ContentView::DEBAT, [(int) $debat->getId()])[$debat->getId()] ?? null,
            'readKind' => 'debat',
            'obligations' => $this->obligations,
            'officialSources' => \App\Service\DebatObligations::sourcesFor($debat->getTheme()),
            'roots' => $arguments->findVisibleRoots($debat),
            'counts' => $arguments->countVisibleByStance($debat),
            'form' => $form,
            'replyTo' => $request->query->getInt('repondre') ?: null,
            'propositions' => $debat->getPropositions()->filter(fn ($p) => $p->isValidated()),
        ], new Response(status: $status));
    }

    /** Thème prédéfini, ou thème libre (« Autre ») de 3 à 40 caractères, modéré. Ajoute une erreur sinon. */
    private function resolveTheme(string $theme, string $other, ContentModerator $moderator, array &$errors): ?string
    {
        if (in_array($theme, \App\Entity\Proposition::THEMES, true)) {
            return $theme;
        }
        if ('__autre' !== $theme && '' !== $theme && !in_array($theme, ['__autre'], true)) {
            // thème libre déjà existant (ré-édition) : on le traite comme un thème libre
            $other = $other ?: $theme;
        }
        $other = trim(preg_replace('/\s+/u', ' ', $other) ?? '');
        if ('' === $other) {
            $errors[] = 'Choisissez un thème, ou saisissez le vôtre via « Autre thème ».';

            return null;
        }
        if (!preg_match('/^[\p{L}0-9][\p{L}0-9\' \-]{2,39}$/u', $other)) {
            $errors[] = 'Le thème libre fait 3 à 40 caractères : lettres, chiffres, espaces, apostrophes ou tirets.';

            return null;
        }
        if ($moderator->analyze($other) !== []) {
            $errors[] = 'Le thème proposé contient un terme non autorisé.';

            return null;
        }
        foreach (\App\Entity\Proposition::THEMES as $t) {
            if (0 === strcasecmp($t, $other)) {
                return $t;
            }
        }

        return mb_strtoupper(mb_substr($other, 0, 1)) . mb_substr($other, 1);
    }
}
