<?php

namespace App\Controller\Admin;

use App\Service\Backup\DatabaseBackup;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Sauvegardes de la base : état, lancement manuel, téléchargement. Réservé aux administrateurs. */
#[IsGranted('ROLE_ADMIN')]
class BackupController extends AbstractController
{
    #[Route('/admin/sauvegardes', name: 'admin_backups', methods: ['GET'])]
    public function index(DatabaseBackup $backup): Response
    {
        return $this->render('admin/backups.html.twig', ['files' => $backup->files(), 'state' => $backup->state(), 'problems' => $backup->problems()]);
    }

    #[Route('/admin/sauvegardes/lancer', name: 'admin_backups_run', methods: ['POST'])]
    public function run(Request $request, DatabaseBackup $backup): Response
    {
        if (!$this->isCsrfTokenValid('backup_run', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('admin_backups');
        }
        $s = $backup->run();
        $this->addFlash($s['ok'] ? 'success' : 'danger', $s['ok'] ? sprintf('Sauvegarde créée : %s (%d Ko).', $s['file'], intdiv($s['size'], 1024)) : 'Échec de la sauvegarde : ' . $s['error']);

        return $this->redirectToRoute('admin_backups');
    }

    #[Route('/admin/sauvegardes/telecharger/{name}', name: 'admin_backups_download', requirements: ['name' => 'lfed_[0-9_-]+\.sql\.gz'], methods: ['GET'])]
    public function download(string $name, DatabaseBackup $backup): Response
    {
        $path = $backup->path($name) ?? throw $this->createNotFoundException();
        $r = new BinaryFileResponse($path);
        $r->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $name);
        $r->headers->set('Cache-Control', 'private, no-store');

        return $r;
    }
}
