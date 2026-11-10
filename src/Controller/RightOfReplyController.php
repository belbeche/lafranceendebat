<?php

namespace App\Controller;

use App\Entity\Dossier;
use App\Entity\NewsItem;
use App\Entity\Revendication;
use App\Entity\RightOfReply;
use App\Entity\Tribune;
use App\Service\ContentModerator;
use App\Service\NotificationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Droit de reponse public : une personne ou une institution citee peut repondre, sans compte.
 *  - GET|POST /droit-de-reponse/{type}/{id}
 * La reponse n'est jamais publiee seule : elle attend la relecture de la moderation
 * (admin_replies), puis s'affiche a cote du contenu concerne.
 */
class RightOfReplyController extends AbstractController
{
    #[Route('/droit-de-reponse/{type}/{id}', name: 'app_reply_new', requirements: ['type' => 'actualite|dossier|revendication|tribune', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(string $type, int $id, Request $request, EntityManagerInterface $em, ContentModerator $moderator, NotificationMailer $notifier, RateLimiterFactory $contactIpLimiter): Response
    {
        [$entity, $title, $backUrl] = $this->resolve($type, $id, $em);
        $errors = [];
        $v = ['name' => '', 'role' => '', 'email' => '', 'body' => ''];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reply' . $type . $id, (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée. Rechargez la page et réessayez.';
            }
            // Champ piege : un humain ne le voit pas, un robot le remplit.
            if ('' !== trim((string) $request->request->get('website'))) {
                return $this->redirect($backUrl);
            }
            foreach (array_keys($v) as $k) {
                $v[$k] = trim((string) $request->request->get($k));
            }
            if (mb_strlen($v['name']) < 2 || mb_strlen($v['name']) > 120) { $errors[] = 'Nom de la personne ou de l\'institution : 2 à 120 caractères.'; }
            if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'Adresse e-mail valide requise (elle ne sera pas publiée).'; }
            if (mb_strlen($v['body']) < 20 || mb_strlen($v['body']) > 2000) { $errors[] = 'Réponse : 20 à 2 000 caractères.'; }
            if (!$request->request->getBoolean('accord')) { $errors[] = 'Cochez la case pour confirmer que vous êtes la personne ou l\'institution concernée.'; }
            if (!$errors && $moderator->containsInsult($v['name'] . "\n" . $v['role'] . "\n" . $v['body'])) {
                $errors[] = 'Votre réponse contient des termes que la charte n\'accepte pas (insulte, menace). Reformulez-la : un droit de réponse porte sur les faits.';
            }
            if (!$errors && !$contactIpLimiter->create($request->getClientIp() ?? 'x')->consume()->isAccepted()) {
                $errors[] = 'Trop de messages depuis cette connexion. Réessayez dans une heure.';
            }
            if (!$errors) {
                $r = (new RightOfReply($type, (int) $entity->getId(), $v['name'], $v['email'], $v['body']))->setAuthorRole($v['role']);
                $em->persist($r);
                $em->flush();
                $notifier->replySubmitted($r, $title);
                $this->addFlash('success', 'Merci. Votre réponse est enregistrée. Un modérateur la relit et vérifie votre identité avant de la publier à côté du contenu. Nous pouvons vous écrire à l\'adresse indiquée.');

                return $this->redirect($backUrl);
            }
        }

        return $this->render('reply/new.html.twig', ['title' => $title, 'backUrl' => $backUrl, 'errors' => $errors, 'v' => $v, 'type' => $type, 'id' => $id], new Response(status: $errors ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** @return array{0: object, 1: string, 2: string} */
    private function resolve(string $type, int $id, EntityManagerInterface $em): array
    {
        $found = match ($type) {
            'actualite' => ($e = $em->getRepository(NewsItem::class)->find($id)) && $e->isPublished() ? [$e, $e->getTitle(), $this->generateUrl('app_news_show', ['slug' => $e->getSlug()])] : null,
            'dossier' => ($e = $em->getRepository(Dossier::class)->find($id)) && $e->isPublished() ? [$e, $e->getTitle(), $this->generateUrl('app_dossier_page', ['slug' => $e->getSlug()])] : null,
            'revendication' => ($e = $em->getRepository(Revendication::class)->find($id)) && $e->isPublished() ? [$e, (string) $e->getTitle(), $this->generateUrl('app_revendication_show', ['slug' => $e->getSlug()])] : null,
            default => ($e = $em->getRepository(Tribune::class)->find($id)) && $e->isPublished() ? [$e, $e->getTitle(), $this->generateUrl('app_tribune_show', ['slug' => $e->getSlug()])] : null,
        };
        if (!$found) {
            throw $this->createNotFoundException();
        }

        return $found;
    }
}
