<?php

namespace App\Controller;

use App\Entity\AnonymousContribution;
use App\Service\ContentModerator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Participer sans compte et sans laisser de trace : GET/POST /participer-anonymement.
 *
 * Garde-fous : jeton CSRF, champ piège anti-robot, limite de dépôts par connexion (la clé de limitation est
 * un hachage, l'IP n'est jamais stockée en base), modération automatique du texte, relecture humaine avant publication.
 */
class AnonymousContributionController extends AbstractController
{
    private const MAX_PER_HOUR = 5;

    #[Route('/participer-anonymement', name: 'app_anonymous_contribution', methods: ['GET', 'POST'])]
    public function form(Request $request, EntityManagerInterface $em, ContentModerator $moderator, CacheItemPoolInterface $cache): Response
    {
        $errors = [];
        $v = ['category' => (string) $request->query->get('categorie', 'citoyen'), 'subject' => '', 'message' => '', 'source' => '', 'contact' => ''];
        if ($request->isMethod('POST')) {
            if ('' !== trim((string) $request->request->get('website', ''))) {
                return $this->redirectToRoute('app_anonymous_thanks'); // robot : on feint le succès
            }
            foreach (array_keys($v) as $k) {
                $v[$k] = trim((string) $request->request->get($k, ''));
            }
            if (!$this->isCsrfTokenValid('anon_contrib', (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée. Réessayez.';
            }
            $hit = $cache->getItem('anon_rate_' . hash('sha256', (string) $request->getClientIp()));
            $n = $hit->isHit() ? (int) $hit->get() : 0;
            if ($n >= self::MAX_PER_HOUR) {
                $errors[] = 'Trop de dépôts depuis votre connexion. Réessayez dans une heure.';
            }
            if (mb_strlen($v['subject']) < 5) {
                $errors[] = 'Donnez un titre d\'au moins 5 caractères.';
            }
            if (mb_strlen($v['message']) < 30) {
                $errors[] = 'Décrivez votre contribution en au moins 30 caractères.';
            }
            if ($moderator->analyze($v['subject'] . ' ' . $v['message']) !== []) {
                $errors[] = 'Le texte contient un terme ou une donnée personnelle non autorisés. Reformulez calmement, sans nommer de personne.';
            }
            if (!$errors) {
                $c = (new AnonymousContribution())->setCategory($v['category'])->setSubject($v['subject'])->setMessage($v['message'])->setSourceUrl($v['source'])->setContact($v['contact']);
                $em->persist($c);
                $em->flush();
                $cache->save($hit->set($n + 1)->expiresAfter(3600));

                return $this->redirectToRoute('app_anonymous_thanks');
            }
        }

        return $this->render('anonymous/form.html.twig', ['errors' => $errors, 'v' => $v], new Response(status: $errors ? 422 : 200));
    }

    #[Route('/participer-anonymement/merci', name: 'app_anonymous_thanks', methods: ['GET'])]
    public function thanks(): Response
    {
        return $this->render('anonymous/thanks.html.twig');
    }
}
