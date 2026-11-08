<?php

namespace App\Controller;

use App\Entity\Proposition;
use App\Entity\User;
use App\Form\PropositionType;
use App\Repository\PropositionRepository;
use App\Repository\RevendicationRepository;
use App\Repository\VoteRepository;
use App\Service\ContentModerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur HTTP « PropositionController ».
 *
 * Routes :
 *  - GET /propositions (app_propositions)
 *  - GET,POST /propositions/nouvelle (app_proposition_new)
 *  - GET /propositions/proches (app_proposition_similar)
 *  - GET /propositions/mes-propositions (app_my_propositions)
 *  - GET /propositions/{id} (app_proposition_show)
 *  - GET /propositions/{id}/fiche (app_proposition_sheet)
 * Accès : ROLE_USER.
 * Tout texte public passe par ContentModerator avant d'être enregistré.
 */
#[Route('/propositions')]
class PropositionController extends AbstractController
{
    private const PER_PAGE = 12;

    #[Route('', name: 'app_propositions', methods: ['GET'])]
    public function index(Request $request, PropositionRepository $propositions, VoteRepository $votes): Response
    {
        $theme = (string) $request->query->get('theme', '');
        $theme = in_array($theme, Proposition::THEMES, true) ? $theme : null;
        $page = max(1, $request->query->getInt('page', 1));

        $total = $propositions->countValidated($theme);
        $list = $propositions->findValidatedRanked($theme, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        $user = $this->getUser();

        return $this->render('proposition/index.html.twig', [
            'propositions' => $list,
            'supportedIds' => $votes->findSupportedIds($user instanceof User ? $user : null, $list),
            'theme' => $theme,
            'themes' => Proposition::THEMES,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'total' => $total,
        ]);
    }

    #[Route('/nouvelle', name: 'app_proposition_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ContentModerator $moderator,
        RevendicationRepository $revendications,
        \App\Service\NotificationMailer $notifier,
        \App\Service\DossierCatalog $dossiers,
        \App\Service\Algo\SimilarityService $similarity,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $proposition = new Proposition();
        $proposition->setAuthor($user);

        $revendication = null;
        if ($slug = (string) $request->query->get('revendication', '')) {
            $revendication = $revendications->findPublishedBySlug($slug);
            if ($revendication) {
                $proposition->setRevendication($revendication)->setTheme($revendication->getTheme());
            }
        }

        $dossierChoices = array_flip($dossiers->options($user));
        $form = $this->createForm(PropositionType::class, $proposition, ['dossier_choices' => $dossierChoices]);
        // Formulaire simplifie : le dossier de reference est choisi automatiquement d'apres le theme s'il n'est pas precise.
        if ($request->isMethod('POST') && $dossierChoices) {
            $data = $request->request->all($form->getName());
            $theme = (string) ($data['theme'] ?? '');
            if (empty($data['dossierSlug']) && isset(\App\Service\DossierCatalog::STARTERS[$theme])) {
                $slug = \App\Service\DossierCatalog::starterSlug($theme);
                if (in_array($slug, $dossierChoices, true)) {
                    $data['dossierSlug'] = $slug;
                    $request->request->set($form->getName(), $data);
                }
            }
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Moderation a priori obligatoire : aucune proposition n'est enregistree si le texte est hostile.
            $text = implode("\n", array_filter([
                $proposition->getTitle(), $proposition->getDescription(), $proposition->getProblem(),
                $proposition->getRisks(), $proposition->getPeaceContribution(), $proposition->getFinancing(),
            ]));
            $reasons = $moderator->analyze($text);
            if ($reasons !== []) {
                $this->addFlash('danger', $moderator->explain($reasons));

                return $this->render('proposition/new.html.twig', ['form' => $form, 'revendication' => $revendication], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            // Doublons : si une proposition tres proche existe deja, on la propose d'abord (une seule fois par titre).
            $ackKey = 'dup_ack_' . md5((string) $proposition->getTitle());
            $dups = $request->getSession()->has($ackKey) ? [] : $similarity->nearest((string) $proposition->getTitle(), (string) $proposition->getDescription(), (string) $proposition->getTheme(), null, 3, \App\Service\Algo\SimilarityService::DUPLICATE_THRESHOLD);
            if (!empty($dups)) {
                $request->getSession()->set($ackKey, 1);
                $this->addFlash('warning', 'Une proposition très proche existe déjà. Soutenez-la plutôt que d\'en créer une nouvelle, ou validez à nouveau si la vôtre est différente.');

                return $this->render('proposition/new.html.twig', ['form' => $form, 'revendication' => $revendication, 'duplicates' => $dups]);
            }

            if (!$user->hasAcceptedCharter()) {
                $user->setCharterAcceptedAt(new \DateTimeImmutable());
            }
            $em->persist($proposition);
            $em->flush();
            $notifier->propositionSubmitted($proposition);

            $this->addFlash('success', 'Merci. Votre proposition est enregistrée et sera publiée après relecture par un modérateur.');

            return $this->redirectToRoute('app_proposition_show', ['id' => $proposition->getId()]);
        }

        return $this->render('proposition/new.html.twig', [
            'form' => $form,
            'revendication' => $revendication,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Propositions proches d'un titre en cours de saisie (aide a eviter les doublons). */
    #[Route('/proches', name: 'app_proposition_similar', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function similar(Request $request, \App\Service\Algo\SimilarityService $similarity): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) < 12) {
            return $this->json([]);
        }
        $out = [];
        foreach ($similarity->nearest($q, $q, '', null, 3, 0.25) as $r) {
            $out[] = ['title' => $r['p']->getTitle(), 'url' => $this->generateUrl('app_proposition_show', ['id' => $r['p']->getId()]), 'support' => $r['p']->getSupportCount()];
        }

        return $this->json($out);
    }

    #[Route('/mes-propositions', name: 'app_my_propositions', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function mine(PropositionRepository $propositions): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('proposition/mine.html.twig', ['propositions' => $propositions->findByAuthor($user)]);
    }

    #[Route('/{id}', name: 'app_proposition_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Proposition $proposition, VoteRepository $votes, \Doctrine\ORM\EntityManagerInterface $em, \Symfony\Component\HttpFoundation\Request $request, \App\Service\Audience\ViewTracker $views, \App\Service\Algo\SimilarityService $similarity): Response
    {
        $this->denyUnlessVisible($proposition);
        $user = $this->getUser();
        if (Proposition::STATUS_VALIDATED === $proposition->getStatus()) {
            $views->recordView(\App\Entity\ContentView::PROPOSITION, (int) $proposition->getId(), $request);
        }

        return $this->render('proposition/show.html.twig', [
            'proposition' => $proposition,
            'viewStats' => $views->stats(\App\Entity\ContentView::PROPOSITION, [(int) $proposition->getId()])[$proposition->getId()] ?? null,
            'related' => Proposition::STATUS_VALIDATED === $proposition->getStatus() ? $similarity->relatedTo($proposition) : [],
            'readKind' => 'proposition',
            'supported' => $user instanceof User && $votes->findOneByUserAndProposition($user, $proposition) !== null,
            'endorsements' => $em->getRepository(\App\Entity\ActorEndorsement::class)->findBy(['proposition' => $proposition], ['createdAt' => 'DESC']),
            'amendements' => $proposition->getAmendements()->filter(fn ($a) => $a->isVisible()),
            'isOwner' => $user instanceof User && $proposition->getAuthor()?->getId() === $user->getId(),
        ]);
    }

    /** Fiche imprimable a transmettre a un elu, une administration ou une collectivite. */
    #[Route('/{id}/fiche', name: 'app_proposition_sheet', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function sheet(Proposition $proposition): Response
    {
        $this->denyUnlessVisible($proposition);

        return $this->render('proposition/sheet.html.twig', [
            'proposition' => $proposition,
            'amendements' => $proposition->getAmendements()->filter(fn ($a) => $a->isVisible()),
        ]);
    }

    private function denyUnlessVisible(Proposition $proposition): void
    {
        if ($proposition->isValidated() || $this->isGranted('ROLE_ADMIN')) {
            return;
        }
        $user = $this->getUser();
        if ($user instanceof User && $proposition->getAuthor()?->getId() === $user->getId()) {
            return;
        }

        throw $this->createNotFoundException();
    }
}
