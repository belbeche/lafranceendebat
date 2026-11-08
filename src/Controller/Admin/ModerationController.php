<?php

namespace App\Controller\Admin;

use App\Entity\Signalement;
use App\Repository\ArgumentRepository;
use App\Repository\DiscussionReplyRepository;
use App\Repository\DiscussionRepository;
use App\Repository\PropositionRepository;
use App\Repository\SignalementRepository;
use App\Service\ModerationLocks;
use App\Service\ModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** File de moderation : toute decision est prise par une personne, jamais par un automatisme seul. */
#[Route('/admin/moderation')]
#[IsGranted('ROLE_MODERATOR')]
class ModerationController extends AbstractController
{
    #[Route('', name: 'admin_moderation', methods: ['GET'])]
    public function index(\App\Service\ReliabilityChecker $reliability, PropositionRepository $propositions, ArgumentRepository $arguments, SignalementRepository $reports, ModerationService $moderation, DiscussionRepository $discussions, DiscussionReplyRepository $replies, \Doctrine\ORM\EntityManagerInterface $em, ModerationLocks $locks): Response
    {
        $open = $reports->findOpen(100);
        $targets = [];
        foreach ($open as $r) {
            $targets[$r->getId()] = $moderation->findTarget($r->getTargetType(), $r->getTargetId());
        }

        $pendingTribunes = $em->getRepository(\App\Entity\Tribune::class)->findBy(['status' => \App\Entity\Tribune::STATUS_PENDING], ['createdAt' => 'ASC'], 100);
        if ($reliability->hasAi()) {
            // Avis d'IA mis en cache : seuls les 5 premiers éléments en attente sont analysés à chaque ouverture de la file.
            foreach (array_slice($pendingTribunes, 0, 5) as $t) {
                $reliability->review($t->getBody(), $t->getSourceList());
            }
        }

        return $this->render('admin/moderation/index.html.twig', [
            'propositions' => $propositions->findPending(100),
            'arguments' => $arguments->findPending(100),
            'discussions' => $discussions->findPending(100),
            'replies' => $replies->findPending(100),
            'claims' => $em->getRepository(\App\Entity\Revendication::class)->createQueryBuilder('r')->andWhere('r.isPublished = false')->andWhere('r.submittedBy IS NOT NULL')->andWhere('r.moderationNote IS NULL')->orderBy('r.id', 'ASC')->setMaxResults(100)->getQuery()->getResult(),
            'tribunes' => $pendingTribunes,
            'missionPosts' => $em->getRepository(\App\Entity\MissionPost::class)->findBy(['status' => \App\Entity\MissionPost::PENDING], ['createdAt' => 'ASC'], 100),
            'inspirations' => $em->getRepository(\App\Entity\Inspiration::class)->findBy(['status' => \App\Entity\Inspiration::PENDING], ['createdAt' => 'ASC'], 100),
            'dossiers' => $em->getRepository(\App\Entity\Dossier::class)->findBy(['status' => \App\Entity\Dossier::STATUS_PENDING], ['createdAt' => 'ASC'], 100),
            'locks' => $locks->activeMap(),
            'workload' => $locks->workload(),
            'reasons' => ModerationLocks::REASONS,
            'reports' => $open,
            'targets' => $targets,
        ]);
    }

    #[Route('/prendre/{kind}/{id}', name: 'admin_moderation_take', requirements: ['kind' => 'proposition|argument|discussion|reponse|revendication|tribune|annonce|contributeur|dossier', 'id' => '\d+'], methods: ['POST'])]
    public function take(string $kind, int $id, Request $request, ModerationLocks $locks): Response
    {
        if (!$this->isCsrfTokenValid('take' . $kind . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');
        } elseif ($locks->take($kind, $id, $this->getUser())) {
            $this->addFlash('success', 'Contenu pris en charge pendant ' . \App\Entity\ModerationLock::MINUTES . ' minutes.');
        } else {
            $this->addFlash('warning', 'Ce contenu est déjà pris en charge par un collègue.');
        }

        return $this->redirectToRoute('admin_moderation');
    }

    #[Route('/{type}/{id}/{decision}', name: 'admin_moderation_decide', requirements: ['type' => 'proposition|argument|discussion|reponse', 'id' => '\d+', 'decision' => 'approve|reject'], methods: ['POST'])]
    public function decide(string $type, int $id, string $decision, Request $request, ModerationService $moderation): Response
    {
        if (!$this->isCsrfTokenValid('moderate' . $type . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('admin_moderation');
        }
        $target = $moderation->findTarget($type, $id) ?? throw $this->createNotFoundException();
        if (!$this->isGranted('ROLE_ADMIN') && method_exists($target, 'getAuthor') && $target->getAuthor() === $this->getUser()) {
            $this->addFlash('warning', 'Vous ne pouvez pas modérer vos propres contributions : un autre modérateur s\'en chargera.');

            return $this->redirectToRoute('admin_moderation');
        }

        if ($decision === 'approve') {
            $moderation->approve($target);
            $this->addFlash('success', 'Contenu publié.');
        } else {
            $note = trim((string) $request->request->get('note', ''));
            if ($note === '') {
                $this->addFlash('warning', 'Un rejet doit être motivé : indiquez le motif communiqué à l\'auteur.');

                return $this->redirectToRoute('admin_moderation');
            }
            $moderation->reject($target, mb_substr($note, 0, 500));
            $this->addFlash('success', 'Contenu rejeté, motif enregistré.');
        }

        return $this->redirectToRoute('admin_moderation');
    }

    /** Demandes de contribuables : un modérateur (ou l'administrateur) publie ou refuse avec motif. */
    #[Route('/revendication/{id}/{decision}', name: 'admin_moderation_claim', requirements: ['id' => '\d+', 'decision' => 'approve|reject'], methods: ['POST'])]
    public function claim(\App\Entity\Revendication $claim, string $decision, Request $request, \Doctrine\ORM\EntityManagerInterface $em, \App\Service\NotificationMailer $notifier): Response
    {
        if (!$this->isCsrfTokenValid('moderateclaim' . $claim->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('admin_moderation');
        }
        if ($claim->getSubmittedBy() === $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('warning', 'Vous ne pouvez pas modérer votre propre demande.');

            return $this->redirectToRoute('admin_moderation');
        }
        $note = trim((string) $request->request->get('note', ''));
        if ('reject' === $decision && '' === $note) {
            $this->addFlash('warning', 'Un refus doit être motivé : indiquez le motif communiqué à l\'auteur.');

            return $this->redirectToRoute('admin_moderation');
        }
        $approve = 'approve' === $decision;
        $claim->setIsPublished($approve)->setUpdatedAt(new \DateTimeImmutable());
        $claim->setModerationNote($approve ? null : mb_substr($note, 0, 500));
        if ($approve) {
            $claim->setModerationNote(null);
        }
        $em->flush();
        $notifier->claimDecision($claim, $approve, $note);
        $this->addFlash('success', $approve ? 'Demande publiée.' : 'Demande refusée, motif enregistré.');

        return $this->redirectToRoute('admin_moderation');
    }

    /** Tribunes de journalistes : publication ou refus motivé, jamais par l'auteur lui-même. */
    #[Route('/tribune/{id}/{decision}', name: 'admin_moderation_tribune', requirements: ['id' => '\d+', 'decision' => 'approve|reject'], methods: ['POST'])]
    public function tribune(\App\Entity\Tribune $t, string $decision, Request $request, EntityManagerInterface $em, \App\Service\NotificationMailer $notifier): Response
    {
        if (!$this->isCsrfTokenValid('moderatetribune' . $t->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('admin_moderation');
        }
        if ($t->getAuthor() === $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('warning', 'Vous ne pouvez pas modérer votre propre tribune.');

            return $this->redirectToRoute('admin_moderation');
        }
        $note = trim((string) $request->request->get('note', ''));
        if ('reject' === $decision && '' === $note) {
            $this->addFlash('warning', 'Un refus doit être motivé.');

            return $this->redirectToRoute('admin_moderation');
        }
        if ('approve' === $decision) {
            $t->publish();
        } else {
            $t->reject($note);
        }
        $em->flush();
        $notifier->tribuneDecision($t, 'approve' === $decision, $note);
        $this->addFlash('success', 'approve' === $decision ? 'Tribune publiée.' : 'Tribune refusée, motif enregistré.');

        return $this->redirectToRoute('admin_moderation');
    }

    #[Route('/annonce-mission/{id}/{decision}', name: 'admin_moderation_missionpost', requirements: ['id' => '\d+', 'decision' => 'approve|reject'], methods: ['POST'])]
    public function missionPost(\App\Entity\MissionPost $p, string $decision, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('moderatemp' . $p->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('admin_moderation');
        }
        if ($p->getAuthor() === $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('warning', 'Vous ne pouvez pas valider votre propre annonce.');

            return $this->redirectToRoute('admin_moderation');
        }
        $note = trim((string) $request->request->get('note', ''));
        if ('reject' === $decision && '' === $note) {
            $this->addFlash('warning', 'Un refus doit être motivé.');

            return $this->redirectToRoute('admin_moderation');
        }
        'approve' === $decision ? $p->publish() : $p->reject($note);
        $em->flush();
        $this->addFlash('success', 'approve' === $decision ? 'Annonce publiée.' : 'Annonce refusée, motif enregistré.');

        return $this->redirectToRoute('admin_moderation');
    }

    #[Route('/reinitialisation-mot-de-passe', name: 'admin_moderation_reset_link', methods: ['POST'])]
    public function resetLink(Request $request, EntityManagerInterface $em, \App\Service\PasswordResetMailer $mailer): Response
    {
        if (!$this->isCsrfTokenValid('admin_reset_link', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('admin_moderation');
        }
        $email = mb_strtolower(trim((string) $request->request->get('email', '')));
        $user = '' !== $email ? $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => $email]) : null;
        if (!$user) {
            $this->addFlash('warning', 'Aucun compte avec cette adresse.');
        } elseif ($user !== $this->getUser() && array_intersect(['ROLE_ADMIN'], $user->getRoles()) && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('warning', 'Seul un administrateur peut envoyer ce lien à un compte administrateur.');
        } elseif (null === $mailer->send($user, true, $err)) {
            $this->addFlash('warning', 'mail' === $err ? 'L\'e-mail n\'a pas pu partir (service d\'envoi refusé ou non configuré). Détail dans le journal : /admin/journal.' : 'Aucun envoi, réessayez dans quelques instants.');
        } else {
            $this->addFlash('success', 'Lien de réinitialisation envoyé à ' . $user->getEmail() . ' (valable 1 heure).');
        }

        return $this->redirectToRoute('admin_moderation');
    }

    #[Route('/contributeur/{id}/{decision}', name: 'admin_moderation_inspiration', requirements: ['id' => '\d+', 'decision' => 'approve|reject'], methods: ['POST'])]
    public function inspiration(\App\Entity\Inspiration $i, string $decision, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('moderateinsp' . $i->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('admin_moderation');
        }
        $note = trim((string) $request->request->get('note', ''));
        if ('reject' === $decision && '' === $note) {
            $this->addFlash('warning', 'Un refus doit être motivé.');

            return $this->redirectToRoute('admin_moderation');
        }
        'approve' === $decision ? $i->publish() : $i->reject($note);
        $em->flush();
        $this->addFlash('success', 'approve' === $decision ? 'Fiche publiée.' : 'Fiche refusée, motif enregistré.');

        return $this->redirectToRoute('admin_moderation');
    }

    #[Route('/dossier/{id}/{decision}', name: 'admin_moderation_dossier', requirements: ['id' => '\d+', 'decision' => 'approve|reject'], methods: ['POST'])]
    public function dossier(\App\Entity\Dossier $d, string $decision, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('moderatedossier' . $d->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('admin_moderation');
        }
        if ($d->getAuthor() === $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('warning', 'Vous ne pouvez pas valider votre propre dossier.');

            return $this->redirectToRoute('admin_moderation');
        }
        $note = trim((string) $request->request->get('note', ''));
        if ('reject' === $decision && '' === $note) {
            $this->addFlash('warning', 'Un refus doit être motivé.');

            return $this->redirectToRoute('admin_moderation');
        }
        'approve' === $decision ? $d->publish() : $d->reject($note);
        $em->flush();
        $this->addFlash('success', 'approve' === $decision ? 'Dossier publié.' : 'Dossier refusé, motif enregistré.');

        return $this->redirectToRoute('admin_moderation');
    }

    #[Route('/signalement/{id}/classer', name: 'admin_moderation_dismiss', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function dismiss(Signalement $report, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('dismiss' . $report->getId(), (string) $request->request->get('_token'))) {
            $report->setStatus(Signalement::STATUS_DISMISSED);
            $em->flush();
            $this->addFlash('success', 'Signalement classé sans suite.');
        }

        return $this->redirectToRoute('admin_moderation');
    }
}
