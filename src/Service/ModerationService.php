<?php

namespace App\Service;

use App\Entity\Argument;
use App\Entity\Discussion;
use App\Entity\DiscussionReply;
use App\Repository\DiscussionReplyRepository;
use App\Repository\DiscussionRepository;
use App\Entity\Proposition;
use App\Entity\Signalement;
use App\Repository\ArgumentRepository;
use App\Repository\PropositionRepository;
use App\Repository\SignalementRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Regles de moderation a posteriori : un contenu signale par plusieurs personnes distinctes
 * est retire de l'affichage public le temps qu'un moderateur humain l'examine.
 * Aucune decision definitive n'est prise automatiquement.
 */
class ModerationService
{
    public function __construct(
        private TransactionalMailer $mails,
        private EntityManagerInterface $em,
        private SignalementRepository $signalements,
        private PropositionRepository $propositions,
        private ArgumentRepository $arguments,
        private DiscussionRepository $discussions,
        private DiscussionReplyRepository $replies,
    ) {
    }

    /** Retourne le contenu cible, ou null s'il n'existe plus. */
    public function findTarget(string $type, int $id): Proposition|Argument|Discussion|DiscussionReply|\App\Entity\NewsItem|\App\Entity\Dossier|null
    {
        return match ($type) {
            Signalement::TARGET_PROPOSITION => $this->propositions->find($id),
            Signalement::TARGET_ARGUMENT => $this->arguments->find($id),
            Signalement::TARGET_DISCUSSION => $this->discussions->find($id),
            Signalement::TARGET_REPLY => $this->replies->find($id),
            Signalement::TARGET_NEWS => $this->em->getRepository(\App\Entity\NewsItem::class)->find($id),
            Signalement::TARGET_DOSSIER => $this->em->getRepository(\App\Entity\Dossier::class)->find($id),
            default => null,
        };
    }

    /**
     * Applique le seuil de signalement. Retourne true si le contenu a ete remis en revue.
     */
    public function applyReportThreshold(Signalement $signalement): bool
    {
        $count = $this->signalements->countOpenForTarget($signalement->getTargetType(), $signalement->getTargetId());
        if ($count < Signalement::AUTO_REVIEW_THRESHOLD) {
            return false;
        }

        $target = $this->findTarget($signalement->getTargetType(), $signalement->getTargetId());
        // Actualites et dossiers : meme regle (3 personnes differentes), le contenu repasse en brouillon / en attente de relecture.
        if ($target instanceof \App\Entity\NewsItem) {
            if (!$target->isPublished()) {
                return false;
            }
            $target->unpublish();
            $this->em->flush();

            return true;
        }
        if ($target instanceof \App\Entity\Dossier) {
            if (!$target->isPublished()) {
                return false;
            }
            $target->reopen();
            $this->em->flush();

            return true;
        }
        if (!$target || $target->getStatus() !== Proposition::STATUS_VALIDATED) {
            return false;
        }

        $target->setStatus(Proposition::STATUS_PENDING);
        $this->em->flush();

        return true;
    }

    /** Une actualite ou un dossier est republie apres relecture : les signalements ouverts sont clos comme non fondes. */
    public function dismissReportsFor(string $type, int $id): void
    {
        foreach ($this->signalements->findBy(['targetType' => $type, 'targetId' => $id, 'status' => Signalement::STATUS_OPEN]) as $report) {
            $report->setStatus(Signalement::STATUS_DISMISSED);
        }
        $this->em->flush();
    }

    /** Un moderateur valide le contenu : les signalements ouverts sont clos comme non fondes. */
    public function approve(Proposition|Argument|Discussion|DiscussionReply $target): void
    {
        $target->setStatus(Proposition::STATUS_VALIDATED);
        if ($target instanceof Proposition) {
            $target->setModeratedAt(new \DateTimeImmutable());
        }
        $this->closeReports($target, Signalement::STATUS_DISMISSED);
        $this->em->flush();
        if (!$target instanceof Argument) {
            $this->mails->contentDecision($target, true);
        }
    }

    /** Un moderateur rejette le contenu avec un motif explicite renvoye a l'auteur. */
    public function reject(Proposition|Argument|Discussion|DiscussionReply $target, ?string $note = null): void
    {
        $target->setStatus(Proposition::STATUS_REJECTED);
        if ($target instanceof Proposition) {
            $target->setModeratedAt(new \DateTimeImmutable());
            $target->setModerationNote($note);
        } elseif ($target instanceof Discussion || $target instanceof DiscussionReply) {
            $target->setModerationNote($note);
        }
        $this->closeReports($target, Signalement::STATUS_HANDLED);
        $this->em->flush();
        if (!$target instanceof Argument) {
            $this->mails->contentDecision($target, false, $note);
        }
    }

    private function closeReports(Proposition|Argument|Discussion|DiscussionReply $target, string $status): void
    {
        $type = match (true) {
            $target instanceof Proposition => Signalement::TARGET_PROPOSITION,
            $target instanceof Discussion => Signalement::TARGET_DISCUSSION,
            $target instanceof DiscussionReply => Signalement::TARGET_REPLY,
            default => Signalement::TARGET_ARGUMENT,
        };
        foreach ($this->signalements->findBy(['targetType' => $type, 'targetId' => $target->getId(), 'status' => Signalement::STATUS_OPEN]) as $report) {
            $report->setStatus($status);
        }
    }
}
