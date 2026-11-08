<?php

namespace App\Controller;

use App\Entity\Discussion;
use App\Entity\DiscussionReply;
use App\Entity\Gratitude;
use App\Entity\Proposition;
use App\Entity\User;
use App\Form\DiscussionReplyType;
use App\Form\DiscussionType;
use App\Repository\DiscussionReplyRepository;
use App\Repository\DiscussionRepository;
use App\Repository\GratitudeRepository;
use App\Service\ContentModerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * L'Agora : espace communautaire pour preferer la diplomatie a la rebellion.
 * Garde-fous : filtre a priori, relecture humaine des nouveaux comptes, limites de frequence,
 * limite de liens, signalements (3 signalements distincts = retrait provisoire), fils verrouillables.
 */
#[Route('/communaute')]
class CommunityController extends AbstractController
{
    private const MAX_LINKS = 2;
    private const MAX_THREADS_PER_DAY = 3;
    private const MAX_REPLIES_PER_HOUR = 8;

    #[Route('', name: 'app_community', methods: ['GET'])]
    public function index(Request $request, DiscussionRepository $discussions): Response
    {
        $category = (string) $request->query->get('categorie', '');
        $category = isset(Discussion::CATEGORIES[$category]) ? $category : null;
        $list = $discussions->findPublic($category);

        return $this->render('community/index.html.twig', [
            'discussions' => $list,
            'counts' => $discussions->replyCounts($list),
            'categories' => Discussion::CATEGORIES,
            'category' => $category,
        ]);
    }

    #[Route('/nouveau', name: 'app_community_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $em, ContentModerator $moderator, DiscussionRepository $discussions): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $discussion = new Discussion();
        $form = $this->createForm(DiscussionType::class, $discussion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($discussions->countSince($user, new \DateTimeImmutable('-24 hours')) >= self::MAX_THREADS_PER_DAY) {
                $form->addError(new \Symfony\Component\Form\FormError('Vous avez atteint la limite de 3 fils par 24 heures. Revenez demain ou répondez à un fil existant.'));
            } elseif ($error = $this->guard($moderator, $discussion->getTitle() . "\n" . $discussion->getBody())) {
                $form->addError(new \Symfony\Component\Form\FormError($error));
            } else {
                $trusted = $this->isGranted('ROLE_ADMIN') || $this->accountAgeDays($user) >= 3;
                $discussion->setAuthor($user)->setStatus($trusted ? Proposition::STATUS_VALIDATED : Proposition::STATUS_PENDING);
                $em->persist($discussion);
                $em->flush();
                $this->addFlash('success', $trusted
                    ? 'Votre fil est publié. Merci d\'inviter au dialogue.'
                    : 'Merci. Votre compte est récent : un modérateur relit votre premier fil avant publication, pour protéger la communauté.');

                return $this->redirectToRoute($trusted ? 'app_community_show' : 'app_community', $trusted ? ['id' => $discussion->getId()] : []);
            }
        }

        return $this->render('community/new.html.twig', ['form' => $form], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}', name: 'app_community_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, DiscussionRepository $discussions, DiscussionReplyRepository $replies, GratitudeRepository $gratitudes): Response
    {
        $discussion = $this->findVisible($id, $discussions);

        return $this->renderShow($discussion, $replies, $gratitudes, $this->createForm(DiscussionReplyType::class, new DiscussionReply()));
    }

    /** Nouvelles reponses depuis la derniere vue (interrogation periodique, meme regles de visibilite que la page). */
    #[Route('/{id}/direct', name: 'app_community_live', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function live(int $id, Request $request, DiscussionRepository $discussions, DiscussionReplyRepository $replies, GratitudeRepository $gratitudes): Response
    {
        $discussion = $this->findVisible($id, $discussions);
        $after = max(0, (int) $request->query->get('apres', 0));
        $new = array_values(array_filter($replies->findVisible($discussion), static fn (DiscussionReply $r) => $r->getId() > $after));
        $user = $this->getUser();
        $html = '';
        foreach ($new as $r) {
            $html .= $this->renderView('community/_reply.html.twig', [
                'r' => $r, 'thanks' => $gratitudes->countsFor([$r]), 'thanked' => $gratitudes->thankedIds($user instanceof User ? $user : null, [$r]),
            ]);
        }
        $resp = $this->json(['html' => $html, 'count' => count($new), 'last' => $new ? end($new)->getId() : $after, 'locked' => $discussion->isLocked()]);
        $resp->headers->set('Cache-Control', 'no-store, private');

        return $resp;
    }

    #[Route('/{id}/repondre', name: 'app_community_reply', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reply(int $id, Request $request, DiscussionRepository $discussions, DiscussionReplyRepository $replies, GratitudeRepository $gratitudes, EntityManagerInterface $em, ContentModerator $moderator, \App\Service\NotificationMailer $notifier): Response
    {
        $discussion = $this->findVisible($id, $discussions);
        if ($discussion->isLocked()) {
            $this->addFlash('warning', 'Ce fil est verrouillé par la modération : il n\'accepte plus de réponses.');

            return $this->redirectToRoute('app_community_show', ['id' => $id]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $reply = new DiscussionReply();
        $form = $this->createForm(DiscussionReplyType::class, $reply);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($replies->countSince($user, new \DateTimeImmutable('-1 hour')) >= self::MAX_REPLIES_PER_HOUR) {
                $form->addError(new \Symfony\Component\Form\FormError('Trop de réponses en peu de temps. Prenez une pause, relisez, et revenez dans quelques minutes.'));
            } elseif ($error = $this->guard($moderator, $reply->getBody() . "\n" . $reply->getReformulation())) {
                $form->addError(new \Symfony\Component\Form\FormError($error));
            } else {
                $trusted = $this->isGranted('ROLE_ADMIN') || $this->accountAgeDays($user) >= 1;
                $reply->setDiscussion($discussion)->setAuthor($user)->setStatus($trusted ? Proposition::STATUS_VALIDATED : Proposition::STATUS_PENDING);
                $em->persist($reply);
                if ($trusted) {
                    $discussion->touch();
                }
                $em->flush();
                if ($trusted) {
                    $notifier->discussionReplied($discussion, $reply);
                }
                $this->addFlash('success', $trusted ? 'Votre réponse est publiée.' : 'Merci. Votre compte est récent : un modérateur relit votre réponse avant publication.');

                return $this->redirectToRoute('app_community_show', array_filter(['id' => $id, '_fragment' => $trusted ? 'reponse-' . $reply->getId() : null]));
            }
        }

        return $this->renderShow($discussion, $replies, $gratitudes, $form, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('/reponse/{id}/merci', name: 'app_community_thank', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function thank(DiscussionReply $reply, Request $request, GratitudeRepository $gratitudes, EntityManagerInterface $em): Response
    {
        $back = $this->redirectToRoute('app_community_show', ['id' => $reply->getDiscussion()?->getId(), '_fragment' => 'reponse-' . $reply->getId()]);
        if (!$this->isCsrfTokenValid('thank' . $reply->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $back;
        }
        /** @var User $user */
        $user = $this->getUser();
        $author = $reply->getAuthor();
        if (!$reply->isVisible() || !$reply->getDiscussion()?->isVisible()) {
            throw $this->createNotFoundException();
        }
        if (!$author || $author->getId() === $user->getId()) {
            $this->addFlash('warning', 'On ne se remercie pas soi-même.');

            return $back;
        }

        $existing = $gratitudes->findOneBy(['reply' => $reply, 'user' => $user]);
        if ($existing) {
            $em->remove($existing);
        } else {
            if ($gratitudes->countGiverToAuthor($user, $author) >= 3) {
                $this->addFlash('info', 'Vous avez déjà remercié cette personne plusieurs fois : pour garder le classement équitable, la limite est de 3 remerciements par personne.');

                return $back;
            }
            $em->persist(new Gratitude($reply, $user));
        }
        $em->flush();

        return $back;
    }

    private function findVisible(int $id, DiscussionRepository $discussions): Discussion
    {
        $discussion = $discussions->find($id);
        if (!$discussion || !$discussion->isVisible()) {
            throw $this->createNotFoundException();
        }

        return $discussion;
    }

    private function renderShow(Discussion $discussion, DiscussionReplyRepository $replies, GratitudeRepository $gratitudes, FormInterface $form, int $status = Response::HTTP_OK): Response
    {
        $list = $replies->findVisible($discussion);
        $user = $this->getUser();

        return $this->render('community/show.html.twig', [
            'discussion' => $discussion,
            'replies' => $list,
            'thanks' => $gratitudes->countsFor($list),
            'thanked' => $gratitudes->thankedIds($user instanceof User ? $user : null, $list),
            'form' => $form,
        ], new Response(status: $status));
    }

    /** Retourne un message d'erreur pedagogique, ou null si le texte est publiable. */
    private function guard(ContentModerator $moderator, string $text): ?string
    {
        $reasons = $moderator->analyze($text);
        if ($reasons) {
            return $moderator->explain($reasons);
        }
        if (preg_match_all('~(?:https?://|www\.)~i', $text) > self::MAX_LINKS) {
            return 'Pas plus de 2 liens par message : citez vos sources principales.';
        }

        return null;
    }

    private function accountAgeDays(User $user): int
    {
        $created = $user->getCreatedAt();

        return $created ? (int) $created->diff(new \DateTimeImmutable())->days : 0;
    }
}
