<?php

namespace App\Controller;

use App\Entity\Debat;
use App\Entity\Proposition;
use App\Entity\Revendication;
use App\Entity\Tribune;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface as U;

/** sitemap.xml : uniquement des pages publiques et publiées. */
class SeoController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(EntityManagerInterface $em): Response
    {
        $urls = [];
        foreach (['app_home', 'app_debats', 'app_propositions', 'app_taxpayers', 'app_revendications', 'app_missions', 'app_tribunes', 'app_dossiers', 'app_inspirations', 'app_charter', 'app_method', 'app_plan_marshall', 'app_institutional_routes', 'app_ranking'] as $r) {
            try {
                $urls[] = [$this->generateUrl($r, [], U::ABSOLUTE_URL), null];
            } catch (\Throwable) {
            }
        }
        foreach (['petition', 'education', 'constitution', 'appel-paix', 'histoire-guerres', 'terrorisme-memoire', 'ils-ont-change-le-monde', 'ia-controle-humain'] as $s) {
            $urls[] = [$this->generateUrl('app_dossier', ['slug' => $s], U::ABSOLUTE_URL), null];
        }
        foreach ($em->getRepository(Debat::class)->findBy(['status' => [Debat::STATUS_OPEN]], ['createdAt' => 'DESC'], 500) as $d) {
            $urls[] = [$this->generateUrl('app_debat_show', ['slug' => $d->getSlug()], U::ABSOLUTE_URL), null];
        }
        foreach ($em->getRepository(Revendication::class)->findBy(['isPublished' => true], ['id' => 'DESC'], 1000) as $r) {
            if ($r->getSlug()) {
                $urls[] = [$this->generateUrl('app_revendication_show', ['slug' => $r->getSlug()], U::ABSOLUTE_URL), $r->getUpdatedAt() ?? null];
            }
        }
        foreach ($em->getRepository(Tribune::class)->findBy(['status' => Tribune::STATUS_PUBLISHED], ['publishedAt' => 'DESC'], 500) as $t) {
            $urls[] = [$this->generateUrl('app_tribune_show', ['slug' => $t->getSlug()], U::ABSOLUTE_URL), $t->getPublishedAt()];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as [$loc, $date]) {
            $xml .= '<url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . ($date instanceof \DateTimeInterface ? '<lastmod>' . $date->format('Y-m-d') . '</lastmod>' : '') . '</url>';
        }

        return new Response($xml . '</urlset>', 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
