<?php

namespace App\Controller\Admin;

use App\Entity\Dossier;
use App\Entity\NewsItem;
use App\Entity\Revendication;
use App\Entity\RightOfReply;
use App\Entity\Tribune;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Relecture des droits de reponse :
 *  - GET  /admin/droits-de-reponse               liste (en attente d'abord)
 *  - POST /admin/droits-de-reponse/{id}/publier  publie (avec ou sans identite verifiee)
 *  - POST /admin/droits-de-reponse/{id}/refuser  refuse, motif obligatoire
 *  - POST /admin/droits-de-reponse/{id}/retirer  remet en attente (retrait de l'affichage)
 * Chaque action POST exige un jeton CSRF ("reply" + id).
 */
#[Route('/admin/droits-de-reponse')]
#[IsGranted('ROLE_MODERATOR')]
class RightOfReplyAdminController extends AbstractController
{
    #[Route('', name: 'admin_replies', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $items = $em->getRepository(RightOfReply::class)->createQueryBuilder('r')
            ->orderBy("CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END", 'ASC')->addOrderBy('r.createdAt', 'DESC')
            ->setMaxResults(200)->getQuery()->getResult();
        $titles = [];
        foreach ($items as $r) {
            $titles[$r->getId()] = $this->describe($em, $r);
        }

        return $this->render('admin/replies/index.html.twig', ['items' => $items, 'titles' => $titles]);
    }

    #[Route('/{id<\d+>}/{action}', name: 'admin_reply_decide', requirements: ['action' => 'publier|refuser|retirer'], methods: ['POST'])]
    public function decide(RightOfReply $r, string $action, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('reply' . $r->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('admin_replies');
        }
        if ('publier' === $action) {
            $r->publish($request->request->getBoolean('identite'));
            $this->addFlash('success', 'Réponse publiée à côté du contenu.');
        } elseif ('retirer' === $action) {
            $r->reopen();
            $this->addFlash('success', 'Réponse retirée de l\'affichage (remise en attente).');
        } else {
            $note = trim((string) $request->request->get('note'));
            if (mb_strlen($note) < 5) {
                $this->addFlash('danger', 'Indiquez un motif de refus (5 caractères minimum).');

                return $this->redirectToRoute('admin_replies');
            }
            $r->reject($note);
            $this->addFlash('success', 'Réponse refusée.');
        }
        $em->flush();

        return $this->redirectToRoute('admin_replies');
    }

    /** @return array{title: string, url: ?string} */
    private function describe(EntityManagerInterface $em, RightOfReply $r): array
    {
        $class = ['actualite' => NewsItem::class, 'dossier' => Dossier::class, 'revendication' => Revendication::class, 'tribune' => Tribune::class][$r->getTargetType()];
        $e = $em->getRepository($class)->find($r->getTargetId());
        if (!$e) {
            return ['title' => 'Contenu supprimé', 'url' => null];
        }
        $url = match ($r->getTargetType()) {
            'actualite' => $this->generateUrl('app_news_show', ['slug' => $e->getSlug()]),
            'dossier' => $this->generateUrl('app_dossier_page', ['slug' => $e->getSlug()]),
            'revendication' => $this->generateUrl('app_revendication_show', ['slug' => $e->getSlug()]),
            default => $this->generateUrl('app_tribune_show', ['slug' => $e->getSlug()]),
        };

        return ['title' => (string) $e->getTitle(), 'url' => $url];
    }
}
