<?php

namespace App\Controller;

use App\Entity\NewsItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Actualité du moment, gratuite et sans compte.
 *  - GET /actualites         (app_news)       : liste paginée, la plus récente d'abord
 *  - GET /actualites/{slug}  (app_news_show)  : une actualité, avec sa source
 * Seules les actualités publiées sont visibles ici ; l'écriture et l'import se font dans /admin/actualites.
 */
class NewsController extends AbstractController
{
    private const PER_PAGE = 12;

    #[Route('/actualites', name: 'app_news', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $repo = $em->getRepository(NewsItem::class);
        $total = $repo->count(['status' => NewsItem::PUBLISHED]);
        $items = $repo->findBy(['status' => NewsItem::PUBLISHED], ['publishedOn' => 'DESC', 'id' => 'DESC'], self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        return $this->render('news/index.html.twig', ['items' => $items, 'page' => $page, 'pages' => max(1, (int) ceil($total / self::PER_PAGE)), 'total' => $total]);
    }

    #[Route('/actualites/{slug}', name: 'app_news_show', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(string $slug, EntityManagerInterface $em): Response
    {
        $n = $em->getRepository(NewsItem::class)->findOneBy(['slug' => $slug]);
        // un brouillon n'est visible que par l'équipe, pour relire avant de publier
        if (!$n || (!$n->isPublished() && !$this->isGranted('ROLE_MODERATOR'))) {
            throw $this->createNotFoundException();
        }

        return $this->render('news/show.html.twig', ['n' => $n]);
    }
}
