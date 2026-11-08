<?php

namespace App\Controller;

use App\Entity\OrderMessage;
use App\Entity\ServiceOrder;
use App\Entity\User;
use App\Service\ContentModerator;
use App\Service\NotificationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/** Messagerie d'une commande : acheteur et prestataire uniquement (lecture seule pour l'administration en cas de litige). */
class OrderMessageController extends AbstractController
{
    #[Route('/services/commande/{id<\d+>}/messages', name: 'app_order_messages', methods: ['GET', 'POST'])]
    public function thread(ServiceOrder $order, Request $request, EntityManagerInterface $em, ContentModerator $moderator, RateLimiterFactory $messageLimiter, NotificationMailer $notifier): Response
    {
        /** @var User $u */
        $u = $this->getUser();
        $party = $u === $order->getBuyer() || $u === $order->getOffer()->getProvider();
        if (!$party && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        $canPost = $party && in_array($order->getStatus(), [ServiceOrder::PAID, ServiceOrder::COMPLETED], true);
        $error = null;
        if ($request->isMethod('POST')) {
            $body = trim((string) $request->request->get('body', ''));
            if (!$canPost) {
                throw $this->createAccessDeniedException();
            }
            if (!$this->isCsrfTokenValid('msg' . $order->getId(), (string) $request->request->get('_token'))) {
                $error = 'Session expirée, réessayez.';
            } elseif (mb_strlen($body) < 2 || mb_strlen($body) > 2000) {
                $error = 'Écrivez un message de 2 à 2000 caractères.';
            } elseif (self::leavesPlatform($body)) {
                $error = 'Pour votre sécurité et celle de votre paiement, les échanges restent sur la plateforme : pas d\'e-mail, de numéro de téléphone ni de lien externe.';
            } elseif ($moderator->containsInsult($body)) {
                $error = 'Ce message contient des termes contraires à la charte de paix.';
            } elseif (!$messageLimiter->create('u' . $u->getId())->consume()->isAccepted()) {
                $error = 'Trop de messages envoyés. Patientez un peu.';
            } else {
                $em->persist(new OrderMessage($order, $u, $body));
                $em->flush();
                $notifier->newMessage($order, $u);

                return $this->redirectToRoute('app_order_messages', ['id' => $order->getId()]);
            }
        }
        $messages = $em->getRepository(OrderMessage::class)->findBy(['order' => $order], ['createdAt' => 'ASC', 'id' => 'ASC']);

        return $this->render('marketplace/messages.html.twig', ['order' => $order, 'messages' => $messages, 'canPost' => $canPost, 'error' => $error, 'me' => $u]);
    }

    #[Route('/services/commande/{id<\d+>}/flux', name: 'app_order_feed', methods: ['GET'])]
    public function feed(ServiceOrder $order, Request $request, EntityManagerInterface $em): \Symfony\Component\HttpFoundation\JsonResponse
    {
        /** @var User $u */
        $u = $this->getUser();
        if ($u !== $order->getBuyer() && $u !== $order->getOffer()->getProvider() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
        $after = max(0, (int) $request->query->get('apres', 0));
        $out = [];
        $last = $after;
        foreach ($em->getRepository(OrderMessage::class)->findBy(['order' => $order], ['id' => 'ASC']) as $m) {
            if ($m->getId() > $after) {
                $out[] = ['id' => $m->getId(), 'mine' => $m->getAuthor() === $u, 'author' => $m->getAuthor()->getPublicName(), 'at' => $m->getCreatedAt()->format('d/m/Y H:i'), 'body' => $m->getBody()];
                $last = $m->getId();
            }
        }
        $r = $this->json(['messages' => $out, 'last' => $last]);
        $r->headers->set('Cache-Control', 'no-store, private');

        return $r;
    }

    public static function leavesPlatform(string $t): bool
    {
        $flat = preg_replace('/[\s.\-_]/', '', mb_strtolower($t)) ?? '';
        return (bool) (
            preg_match('/[a-z0-9._%+-]+\s*(@|\[at\]|\(at\)| arobase )\s*[a-z0-9.-]+/i', $t)
            || preg_match('/(https?:\/\/|www\.)/i', $t)
            || preg_match('/(\+?\d[\d\s.\-]{7,}\d)/', $t)
            || preg_match('/\d{9,}/', $flat)
            || preg_match('/\b(whatsapp|telegram|signal|snapchat)\b/i', $t)
        );
    }
}
