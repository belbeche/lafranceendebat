<?php

namespace App\Controller;

use App\Entity\Argument;
use App\Entity\Discussion;
use App\Entity\Dossier;
use App\Entity\NewsItem;
use App\Entity\DiscussionReply;
use App\Entity\Proposition;
use App\Entity\Signalement;
use App\Entity\User;
use App\Form\SignalementType;
use App\Repository\SignalementRepository;
use App\Service\ModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur HTTP "SignalementController".
 *
 * Routes :
 *  - GET,POST /signaler/{type}/{id} (app_signalement_new)
 * Accès : ROLE_USER.
 */
#[IsGranted('ROLE_USER')]
class SignalementController extends AbstractController
{
    #[Route('/signaler/{type}/{id}', name: 'app_signalement_new', requirements: ['type' => 'proposition|argument|discussion|reponse|actualite|dossier', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(
        string $type,
        int $id,
        Request $request,
        ModerationService $moderation,
        SignalementRepository $signalements,
        EntityManagerInterface $em,
        \App\Service\NotificationMailer $notifier,
    ): Response {
        $target = $moderation->findTarget($type, $id);
        $published = $target instanceof NewsItem || $target instanceof Dossier ? $target->isPublished() : (null !== $target && $target->getStatus() === Proposition::STATUS_VALIDATED);
        if (!$target || !$published) {
            throw $this->createNotFoundException();
        }

        /** @var User $user */
        $user = $this->getUser();
        [$backRoute, $backParams, $excerpt] = $this->describe($target);

        if ($signalements->hasAlreadyReported($user, $type, $id)) {
            $this->addFlash('info', 'Vous avez déjà signalé ce contenu. Un modérateur l\'examine.');

            return $this->redirectToRoute($backRoute, $backParams);
        }

        $signalement = (new Signalement($type, $id))->setReporter($user);
        $form = $this->createForm(SignalementType::class, $signalement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($signalement);
            $em->flush();
            $notifier->reportSubmitted($signalement);
            $reviewed = $moderation->applyReportThreshold($signalement);

            $this->addFlash('success', $reviewed
                ? 'Merci. Plusieurs personnes ont signalé ce contenu : il est retiré de l\'affichage le temps d\'un examen par un modérateur.'
                : 'Merci. Votre signalement sera examiné par un modérateur. Aucune décision automatique n\'est prise.');

            return $this->redirectToRoute($backRoute, $backParams);
        }

        return $this->render('signalement/new.html.twig', [
            'form' => $form,
            'excerpt' => $excerpt,
            'backRoute' => $backRoute,
            'backParams' => $backParams,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** @return array{0: string, 1: array<string, mixed>, 2: string} */
    private function describe(Proposition|Argument|Discussion|DiscussionReply|NewsItem|Dossier $target): array
    {
        if ($target instanceof NewsItem) {
            return ['app_news_show', ['slug' => $target->getSlug()], $target->getTitle()];
        }
        if ($target instanceof Dossier) {
            return ['app_dossier_page', ['slug' => $target->getSlug()], $target->getTitle()];
        }
        if ($target instanceof Discussion) {
            return ['app_community_show', ['id' => $target->getId()], (string) $target->getTitle()];
        }
        if ($target instanceof DiscussionReply) {
            return ['app_community_show', ['id' => $target->getDiscussion()?->getId(), '_fragment' => 'reponse-' . $target->getId()], mb_substr((string) $target->getBody(), 0, 280)];
        }
        if ($target instanceof Proposition) {
            return ['app_proposition_show', ['id' => $target->getId()], (string) $target->getTitle()];
        }

        return [
            'app_debat_show',
            ['slug' => $target->getDebat()?->getSlug(), '_fragment' => 'argument-' . $target->getId()],
            mb_substr((string) $target->getContent(), 0, 280),
        ];
    }
}
