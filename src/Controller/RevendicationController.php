<?php

namespace App\Controller;

use App\Entity\Proposition;
use App\Entity\Revendication;
use App\Repository\RevendicationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ContentModerator;
use App\Service\NotificationMailer;

/**
 * Contrôleur HTTP « RevendicationController ».
 *
 * Routes :
 *  - GET /revendications (app_revendications)
 *  - GET,POST /revendications/proposer (app_revendication_new)
 *  - GET /revendications/{slug} (app_revendication_show)
 * Accès : ROLE_USER.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 * Tout texte public passe par ContentModerator avant d'être enregistré.
 */
#[Route('/revendications')]
class RevendicationController extends AbstractController
{
    #[Route('', name: 'app_revendications', methods: ['GET'])]
    public function index(Request $request, RevendicationRepository $repo): Response
    {
        $group = (string) $request->query->get('groupe', '');
        $group = in_array($group, Revendication::GROUPS, true) ? $group : null;
        $status = (string) $request->query->get('statut', '');
        $status = isset(Revendication::STATUS_LABELS[$status]) ? $status : null;

        return $this->render('revendication/index.html.twig', [
            'revendications' => $request->query->get('langue') === 'toutes' ? $repo->findPublished($group, $status) : $repo->findPublishedForReader($request->getLocale(), $group, $status),
            'allLangs' => $request->query->get('langue') === 'toutes',
            'groups' => Revendication::GROUPS,
            'group' => $group,
            'statuses' => Revendication::STATUS_LABELS,
            'status' => $status,
            'awaiting' => $repo->countAwaitingAnswer(),
        ]);
    }

    /** Contribution ouverte a tout acteur connecte : meme regle pour tous (source publique, faits, pas d'insulte, relecture avant publication). */
    #[Route('/proposer', name: 'app_revendication_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function propose(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, ContentModerator $moderator, RateLimiterFactory $claimUserLimiter, NotificationMailer $notifier): Response
    {
        $errors = [];
        $d = ['title' => '', 'summary' => '', 'groupLabel' => 'Tous les citoyens', 'theme' => Proposition::THEMES[0], 'sourceUrl' => '', 'sourceLabel' => '', 'authority' => '', 'locale' => $request->getLocale()];
        if ($request->isMethod('POST')) {
            foreach ($d as $k => $v) {
                $d[$k] = trim((string) $request->request->get($k, $v));
            }
            if (!$this->isCsrfTokenValid('claim_new', (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée. Réessayez.';
            }
            if (mb_strlen($d['title']) < 10 || mb_strlen($d['title']) > 190) {
                $errors[] = 'Le titre doit faire entre 10 et 190 caractères.';
            }
            if (mb_strlen($d['summary']) < 60 || mb_strlen($d['summary']) > 3000) {
                $errors[] = 'Décrivez les faits en 60 à 3000 caractères : quoi, qui, quand, avec quelles conséquences.';
            }
            if (!in_array($d['groupLabel'], Revendication::GROUPS, true) || !in_array($d['theme'], Proposition::THEMES, true)) {
                $errors[] = 'Portée ou thème inconnu.';
            }
            if (!preg_match('#^https://[^\s]+\.[^\s]+$#i', $d['sourceUrl'])) {
                $errors[] = 'Une source publique est obligatoire (lien https) : article, rapport, décision, document officiel.';
            }
            if (array_intersect($moderator->analyze($d['title'] . "\n" . $d['summary']), [ContentModerator::REASON_INSULT, ContentModerator::REASON_THREAT, ContentModerator::REASON_PERSONAL_DATA])) {
                $errors[] = 'Le texte contient une insulte, une menace ou une donnée personnelle. Décrivez les faits calmement, sans viser une personne.';
            }
            if (!$errors && !$claimUserLimiter->create('u' . $this->getUser()->getId())->consume()->isAccepted()) {
                $errors[] = 'Limite quotidienne atteinte (5 revendications par jour). Revenez demain.';
            }
            if (!$errors) {
                $base = strtolower((string) $slugger->slug($d['title']));
                $slug = mb_substr($base, 0, 190) ?: 'revendication';
                $n = 1;
                while ($em->getRepository(Revendication::class)->findOneBy(['slug' => $slug])) {
                    $slug = mb_substr($base, 0, 180) . '-' . (++$n);
                }
                $r = (new Revendication())->setTitle($d['title'])->setSlug($slug)->setSummary($d['summary'])->setGroupLabel($d['groupLabel'])->setTheme($d['theme'])
                    ->setSourceUrl($d['sourceUrl'])->setSourceLabel($d['sourceLabel'] ?: null)->setCompetentAuthority($d['authority'] ?: null)->setLocale($d['locale'])
                    ->setSubmittedBy($this->getUser())->setIsPublished(false)->setRaisedAt(new \DateTimeImmutable())->setUpdatedAt(new \DateTimeImmutable());
                $em->persist($r);
                $em->flush();
                $notifier->claimSubmitted($r);
                $this->addFlash('success', 'Merci. Votre revendication est enregistrée et sera publiée après relecture, avec un droit de réponse pour les personnes ou institutions citées.');

                return $this->redirectToRoute('app_revendications');
            }
        }

        return $this->render('revendication/new.html.twig', ['errors' => $errors, 'd' => $d, 'groups' => Revendication::GROUPS, 'themes' => Proposition::THEMES, 'locales' => Revendication::LOCALES]);
    }

    #[Route('/{slug}', name: 'app_revendication_show', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(string $slug, RevendicationRepository $repo): Response
    {
        $revendication = $repo->findPublishedBySlug($slug) ?? throw $this->createNotFoundException();

        return $this->render('revendication/show.html.twig', [
            'revendication' => $revendication,
            'propositions' => $revendication->getPropositions()->filter(fn (Proposition $p) => $p->isValidated()),
        ]);
    }
}
