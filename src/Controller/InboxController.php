<?php

namespace App\Controller;

use App\Entity\DirectMessage;
use App\Entity\Notification;
use App\Entity\User;
use App\Service\ContentModerator;
use App\Service\NotificationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/** Messagerie directe, cloche de notifications et point d'interrogation périodique (temps réel sans serveur dédié). */
class InboxController extends AbstractController
{
    private const ACTOR_ROLES = ['ROLE_ACTOR', 'ROLE_ELU', 'ROLE_ASSOCIATION', 'ROLE_MODERATOR', 'ROLE_ADMIN'];

    #[Route('/messages', name: 'app_inbox', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        /** @var User $me */
        $me = $this->getUser();
        $all = $em->createQuery('SELECT m FROM App\Entity\DirectMessage m WHERE m.sender = :u OR m.recipient = :u ORDER BY m.id DESC')->setParameter('u', $me)->setMaxResults(400)->getResult();
        $threads = [];
        foreach ($all as $m) {
            $other = $m->getSender() === $me ? $m->getRecipient() : $m->getSender();
            $k = $other->getId();
            $threads[$k] ??= ['user' => $other, 'last' => $m, 'unread' => 0];
            if ($m->getRecipient() === $me && null === $m->getReadAt()) {
                ++$threads[$k]['unread'];
            }
        }

        return $this->render('inbox/index.html.twig', ['threads' => array_values($threads)]);
    }

    #[Route('/messages/{id<\d+>}', name: 'app_inbox_thread', methods: ['GET', 'POST'])]
    public function thread(User $id, Request $request, EntityManagerInterface $em, ContentModerator $moderator, RateLimiterFactory $messageLimiter, NotificationMailer $notifier): Response
    {
        /** @var User $me */
        $me = $this->getUser();
        $other = $id;
        if ($other === $me || !$other->isEnabled()) {
            throw $this->createNotFoundException();
        }
        $repo = $em->getRepository(DirectMessage::class);
        $canWrite = $this->mayWrite($me, $other, $repo);
        $error = null;
        if ($request->isMethod('POST')) {
            $body = trim((string) $request->request->get('body', ''));
            if (!$canWrite) {
                throw $this->createAccessDeniedException();
            }
            if (!$this->isCsrfTokenValid('dm' . $other->getId(), (string) $request->request->get('_token'))) {
                $error = 'Session expirée, réessayez.';
            } elseif (mb_strlen($body) < 2 || mb_strlen($body) > 2000) {
                $error = 'Écrivez un message de 2 à 2000 caractères.';
            } elseif (OrderMessageController::leavesPlatform($body)) {
                $error = 'Les échanges restent sur la plateforme : pas d\'e-mail, de numéro de téléphone ni de lien externe.';
            } elseif ($moderator->containsInsult($body)) {
                $error = 'Ce message contient des termes contraires à la charte de paix.';
            } elseif (!$messageLimiter->create('dm' . $me->getId())->consume()->isAccepted()) {
                $error = 'Trop de messages envoyés. Patientez un peu.';
            } else {
                $hadUnread = null !== $repo->findOneBy(['sender' => $me, 'recipient' => $other, 'readAt' => null]);
                $em->persist(new DirectMessage($me, $other, $body));
                $em->flush();
                $notifier->directMessage($other, $me, !$hadUnread);

                return $this->redirectToRoute('app_inbox_thread', ['id' => $other->getId(), '_fragment' => 'bas']);
            }
        }
        $messages = $repo->findBy(['sender' => [$me, $other], 'recipient' => [$me, $other]], ['id' => 'ASC']);
        $em->createQuery('UPDATE App\Entity\DirectMessage m SET m.readAt = :n WHERE m.recipient = :me AND m.sender = :o AND m.readAt IS NULL')->setParameters(['n' => new \DateTimeImmutable(), 'me' => $me, 'o' => $other])->execute();

        return $this->render('inbox/thread.html.twig', ['other' => $other, 'messages' => $messages, 'me' => $me, 'canWrite' => $canWrite, 'error' => $error, 'last' => $messages ? end($messages)->getId() : 0]);
    }

    /** Nouveaux messages de l'interlocuteur depuis le dernier vu (marqués lus). */
    #[Route('/messages/{id<\d+>}/flux', name: 'app_inbox_feed', methods: ['GET'])]
    public function feed(User $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $after = max(0, (int) $request->query->get('apres', 0));
        $new = $em->createQuery('SELECT m FROM App\Entity\DirectMessage m WHERE m.sender = :o AND m.recipient = :me AND m.id > :a ORDER BY m.id ASC')->setParameters(['o' => $id, 'me' => $me, 'a' => $after])->getResult();
        $out = [];
        foreach ($new as $m) {
            $out[] = ['id' => $m->getId(), 'mine' => false, 'author' => $m->getSender()->getPublicName(), 'at' => $m->getCreatedAt()->format('d/m/Y H:i'), 'body' => $m->getBody()];
        }
        if ($new) {
            $em->createQuery('UPDATE App\Entity\DirectMessage m SET m.readAt = :n WHERE m.recipient = :me AND m.sender = :o AND m.readAt IS NULL')->setParameters(['n' => new \DateTimeImmutable(), 'me' => $me, 'o' => $id])->execute();
        }

        return $this->noStore($this->json(['messages' => $out, 'last' => $new ? end($new)->getId() : $after]));
    }

    #[Route('/notifications', name: 'app_notifications', methods: ['GET'])]
    public function notifications(EntityManagerInterface $em): Response
    {
        /** @var User $me */
        $me = $this->getUser();
        $items = $em->getRepository(Notification::class)->findBy(['user' => $me], ['id' => 'DESC'], 50);
        $html = $this->render('inbox/notifications.html.twig', ['items' => $items]);
        $em->createQuery('UPDATE App\Entity\Notification n SET n.readAt = :n WHERE n.user = :me AND n.readAt IS NULL')->setParameters(['n' => new \DateTimeImmutable(), 'me' => $me])->execute();

        return $html;
    }

    /** Compteurs et dernières notifications non lues, interrogés toutes les quelques secondes par la page. */
    #[Route('/api/live', name: 'app_live', methods: ['GET'])]
    public function live(EntityManagerInterface $em): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $messages = (int) $em->createQuery('SELECT COUNT(m.id) FROM App\Entity\DirectMessage m WHERE m.recipient = :me AND m.readAt IS NULL')->setParameter('me', $me)->getSingleScalarResult();
        $unread = $em->getRepository(Notification::class)->findBy(['user' => $me, 'readAt' => null], ['id' => 'DESC'], 5);

        return $this->noStore($this->json([
            'messages' => $messages,
            'notifications' => (int) $em->createQuery('SELECT COUNT(n.id) FROM App\Entity\Notification n WHERE n.user = :me AND n.readAt IS NULL')->setParameter('me', $me)->getSingleScalarResult(),
            'latest' => array_map(static fn (Notification $n) => ['id' => $n->getId(), 'text' => $n->getText(), 'url' => $n->getUrl()], $unread),
        ]));
    }

    /** Dernières notifications, lues ou non, pour le panneau de la cloche. */
    #[Route('/api/notifications', name: 'app_api_notifications', methods: ['GET'])]
    public function apiList(EntityManagerInterface $em): JsonResponse
    {
        /** @var User $me */
        $me = $this->getUser();
        $items = $em->getRepository(Notification::class)->findBy(['user' => $me], ['id' => 'DESC'], 20);

        return $this->noStore($this->json(['items' => array_map(static fn (Notification $n) => ['id' => $n->getId(), 'text' => $n->getText(), 'url' => $n->getUrl(), 'at' => $n->getCreatedAt()->format('c'), 'read' => $n->isRead()], $items)]));
    }

    /** Marque comme lues les notifications données (ou toutes si aucune n'est précisée). Jeton CSRF dans l'en-tête X-CSRF-Token. */
    #[Route('/api/notifications/lues', name: 'app_api_notifications_read', methods: ['POST'])]
    public function apiRead(Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('notif', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['ok' => false], 403);
        }
        /** @var User $me */
        $me = $this->getUser();
        $ids = array_values(array_filter(array_map('intval', (array) (json_decode($request->getContent(), true)['ids'] ?? [])), static fn (int $i) => $i > 0));
        $dql = 'UPDATE App\Entity\Notification n SET n.readAt = :n WHERE n.user = :me AND n.readAt IS NULL' . ($ids ? ' AND n.id IN (:ids)' : '');
        $q = $em->createQuery($dql)->setParameter('n', new \DateTimeImmutable())->setParameter('me', $me);
        if ($ids) {
            $q->setParameter('ids', $ids);
        }
        $q->execute();

        return $this->json(['ok' => true]);
    }

    private function mayWrite(User $me, User $other, $repo): bool
    {
        // La messagerie sert les acteurs publics : au moins l'une des deux personnes doit en être un. Citoyen avec citoyen : refusé.
        return (bool) array_intersect($me->getRoles(), self::ACTOR_ROLES) || (bool) array_intersect($other->getRoles(), self::ACTOR_ROLES);
    }

    private function noStore(JsonResponse $r): JsonResponse
    {
        $r->headers->set('Cache-Control', 'no-store, private');

        return $r;
    }
}
