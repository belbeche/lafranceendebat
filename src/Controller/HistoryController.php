<?php

namespace App\Controller;

use App\Entity\Argument;
use App\Entity\Debat;
use App\Entity\Discussion;
use App\Entity\DiscussionReply;
use App\Entity\Proposition;
use App\Entity\Revendication;
use App\Service\RevisionRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Historique public des modifications d'un contenu publie : ce qui a change, quand, et par qui (auteur ou moderation). */
class HistoryController extends AbstractController
{
    private const LABELS = [
        'title' => 'Titre', 'theme' => 'Thème', 'summary' => 'Exposé', 'facts' => 'Faits et sources', 'authority' => 'Autorité compétente', 'outcome' => 'Compte rendu',
        'content' => 'Texte', 'sourceUrl' => 'Source', 'reformulation' => 'Reformulation', 'description' => 'Mesure proposée', 'body' => 'Texte', 'sourceLabel' => 'Nom de la source',
    ];

    #[Route('/historique/{type}/{id}', name: 'app_history', requirements: ['type' => 'debat|argument|proposition|discussion|reponse|revendication', 'id' => '\d+'], methods: ['GET'])]
    public function show(string $type, int $id, EntityManagerInterface $em, RevisionRecorder $revisions): Response
    {
        [$class, $visible, $title, $back] = match ($type) {
            'debat' => [Debat::class, fn ($e) => \in_array($e->getStatus(), [Debat::STATUS_OPEN, Debat::STATUS_CLOSED], true), fn ($e) => $e->getTitle(), fn ($e) => $this->generateUrl('app_debat_show', ['slug' => $e->getSlug()])],
            'argument' => [Argument::class, fn ($e) => $e->isVisible() && \in_array($e->getDebat()?->getStatus(), [Debat::STATUS_OPEN, Debat::STATUS_CLOSED], true), fn ($e) => 'Argument dans « ' . $e->getDebat()?->getTitle() . ' »', fn ($e) => $this->generateUrl('app_debat_show', ['slug' => $e->getDebat()->getSlug(), '_fragment' => 'argument-' . $e->getId()])],
            'proposition' => [Proposition::class, fn ($e) => $e->isValidated(), fn ($e) => $e->getTitle(), fn ($e) => $this->generateUrl('app_proposition_show', ['id' => $e->getId()])],
            'discussion' => [Discussion::class, fn ($e) => Proposition::STATUS_VALIDATED === $e->getStatus(), fn ($e) => $e->getTitle(), fn ($e) => $this->generateUrl('app_community_show', ['id' => $e->getId()])],
            'reponse' => [DiscussionReply::class, fn ($e) => Proposition::STATUS_VALIDATED === $e->getStatus(), fn ($e) => 'Réponse dans « ' . $e->getDiscussion()?->getTitle() . ' »', fn ($e) => $this->generateUrl('app_community_show', ['id' => $e->getDiscussion()->getId(), '_fragment' => 'reponse-' . $e->getId()])],
            default => [Revendication::class, fn ($e) => $e->isPublished(), fn ($e) => $e->getTitle(), fn ($e) => $this->generateUrl('app_revendication_show', ['slug' => $e->getSlug()])],
        };
        $entity = $em->getRepository($class)->find($id);
        if (!$entity || !$visible($entity)) {
            throw $this->createNotFoundException();
        }

        return $this->render('history/show.html.twig', ['title' => $title($entity), 'back' => $back($entity), 'revisions' => $revisions->history($type, $id), 'labels' => self::LABELS]);
    }
}
