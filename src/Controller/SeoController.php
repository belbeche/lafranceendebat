<?php

namespace App\Controller;

use App\Entity\Debat;
use App\Entity\Proposition;
use App\Entity\Revendication;
use App\Entity\Tribune;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface as U;

/** sitemap.xml : uniquement des pages publiques et publiées. */
class SeoController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(Request $request, EntityManagerInterface $em): Response
    {
        // Les URL du sitemap doivent être en https et sur le même domaine que le site (Google Search Console les compare à la propriété).
        // Derrière un proxy mal déclaré, Symfony croit être en http : on fixe donc le schéma ici, sauf en local.
        $host = $request->getHost();
        $isLocal = 'localhost' === $host || str_ends_with($host, '.localhost') || '127.0.0.1' === $host;
        $base = $isLocal ? $request->getSchemeAndHttpHost() : 'https://' . $host;
        $abs = fn (string $route, array $params = []): string => $base . $this->generateUrl($route, $params, U::ABSOLUTE_PATH);
        $urls = [];
        foreach (['app_home', 'app_debats', 'app_propositions', 'app_taxpayers', 'app_revendications', 'app_missions', 'app_tribunes', 'app_dossiers', 'app_news', 'app_statistics', 'app_departments', 'app_pillars', 'app_model', 'app_inspirations', 'app_charter', 'app_how_it_works', 'app_anonymous_contribution', 'app_parties', 'app_positions', 'app_support', 'app_programmes', 'app_budget_calculator', 'app_open_source', 'app_method', 'app_plan_marshall', 'app_institutional_routes', 'app_ranking'] as $r) {
            try {
                $urls[] = [$abs($r), null];
            } catch (\Throwable) {
            }
        }
        foreach ($em->getRepository(\App\Entity\NewsItem::class)->findBy(['status' => \App\Entity\NewsItem::PUBLISHED], ['publishedOn' => 'DESC'], 200) as $ni) {
            $urls[] = [$abs('app_news_show', ['slug' => $ni->getSlug()]), $ni->getPublishedOn()->format('Y-m-d')];
        }
        foreach (array_keys(\App\Service\Departments::ALL) as $dc) {
            $urls[] = [$abs('app_department', ['code' => $dc]), null];
        }
        foreach (['petition', 'education', 'constitution', 'appel-paix', 'histoire-guerres', 'terrorisme-memoire', 'ils-ont-change-le-monde', 'ia-controle-humain'] as $s) {
            $urls[] = [$abs('app_dossier', ['slug' => $s]), null];
        }
        foreach ($em->getRepository(Debat::class)->findBy(['status' => [Debat::STATUS_OPEN]], ['createdAt' => 'DESC'], 500) as $d) {
            $urls[] = [$abs('app_debat_show', ['slug' => $d->getSlug()]), null];
        }
        foreach ($em->getRepository(Revendication::class)->findBy(['isPublished' => true], ['id' => 'DESC'], 1000) as $r) {
            if ($r->getSlug()) {
                $urls[] = [$abs('app_revendication_show', ['slug' => $r->getSlug()]), $r->getUpdatedAt() ?? null];
            }
        }
        foreach ($em->getRepository(Tribune::class)->findBy(['status' => Tribune::STATUS_PUBLISHED], ['publishedAt' => 'DESC'], 500) as $t) {
            $urls[] = [$abs('app_tribune_show', ['slug' => $t->getSlug()]), $t->getPublishedAt()];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as [$loc, $date]) {
            $xml .= '<url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . ($date instanceof \DateTimeInterface ? '<lastmod>' . $date->format('Y-m-d') . '</lastmod>' : '') . '</url>';
        }

        return new Response($xml . '</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
