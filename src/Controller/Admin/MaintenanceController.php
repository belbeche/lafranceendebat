<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Vider les caches du site depuis l'administration, sans accès au serveur. */
#[IsGranted('ROLE_ADMIN')]
class MaintenanceController extends AbstractController
{
    #[Route('/admin/maintenance/cache', name: 'admin_clear_cache', methods: ['POST'])]
    public function clear(Request $request, #[Autowire(service: 'cache.global_clearer')] CacheClearerInterface $pools, #[Autowire('%kernel.cache_dir%')] string $cacheDir): Response
    {
        if (!$this->isCsrfTokenValid('clearcache', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('admin_rubriques');
        }
        $done = [];
        try {
            $pools->clearCache($cacheDir);
            $done[] = 'caches applicatifs';
        } catch (\Throwable) {
        }
        try {
            (new Filesystem())->remove($cacheDir . '/twig');
            $done[] = 'gabarits compilés';
        } catch (\Throwable) {
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $done[] = 'opcache PHP';
        }
        $this->addFlash('success', 'Vidés : ' . ($done ? implode(', ', $done) : 'rien à vider') . '. Les CSS se rechargent d\'eux-mêmes (version automatique). Fais Ctrl+F5 si le navigateur garde l\'ancien.');

        return $this->redirectToRoute('admin_rubriques');
    }

    #[Route('/admin/maintenance/mode', name: 'admin_maintenance_mode', methods: ['POST'])]
    public function mode(Request $request, \App\Service\MaintenanceMode $maintenance): Response
    {
        if (!$this->isCsrfTokenValid('maintenancemode', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('admin_rubriques');
        }
        if ('on' === $request->request->get('etat')) {
            $minutes = max(0, min(720, (int) $request->request->get('minutes')));
            $maintenance->enable((string) $request->request->get('message'), $minutes ?: null, (string) $this->getUser()?->getUserIdentifier());
            $this->addFlash('warning', 'Maintenance ACTIVÉE : les visiteurs voient la page de mise à jour. Toi, tu navigues normalement. Pense à la désactiver.');
        } else {
            $maintenance->disable();
            $this->addFlash('success', 'Maintenance désactivée : le site est ouvert.');
        }

        return $this->redirectToRoute('admin_rubriques');
    }
}
