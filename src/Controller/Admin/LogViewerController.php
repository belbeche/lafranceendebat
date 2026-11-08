<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Lecture seule des dernières lignes utiles du journal, pour diagnostiquer sans accès SSH. */
#[IsGranted('ROLE_ADMIN')]
class LogViewerController extends AbstractController
{
    #[Route('/admin/journal', name: 'admin_logs', methods: ['GET'])]
    public function index(Request $request, #[Autowire('%kernel.logs_dir%')] string $dir, #[Autowire('%kernel.environment%')] string $env): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $level = (string) $request->query->get('niveau', 'erreurs');
        $file = $dir . '/' . $env . '.log';
        $lines = [];
        if (is_readable($file)) {
            $fh = new \SplFileObject($file, 'r');
            $fh->seek(PHP_INT_MAX);
            $last = $fh->key();
            $start = max(0, $last - 4000);
            for ($i = $start; $i <= $last; ++$i) {
                $fh->seek($i);
                $l = rtrim((string) $fh->current());
                if ('' === $l || str_contains($l, 'deprecation.') || str_contains($l, 'SessionHandler::gc')) {
                    continue;
                }
                if ('erreurs' === $level && !preg_match('/\.(ERROR|CRITICAL|ALERT|EMERGENCY|WARNING)|security\.INFO: (Authentication|Login)|AuthenticationException|Invalid CSRF|TooManyLogin/i', $l)) {
                    continue;
                }
                if ('' !== $q && false === stripos($l, $q)) {
                    continue;
                }
                $lines[] = mb_substr($l, 0, 600);
            }
        }

        return $this->render('admin/logs.html.twig', ['lines' => array_slice(array_reverse($lines), 0, 150), 'q' => $q, 'level' => $level, 'file' => basename($file), 'exists' => is_readable($file)]);
    }
}
