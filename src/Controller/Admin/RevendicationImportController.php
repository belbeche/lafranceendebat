<?php

namespace App\Controller\Admin;

use App\Service\RevendicationImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur HTTP « RevendicationImportController ».
 *
 * Routes :
 *  - GET,POST /admin/import-revendications (admin_revendication_import)
 * Accès : ROLE_ADMIN.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
#[IsGranted('ROLE_ADMIN')]
class RevendicationImportController extends AbstractController
{
    #[Route('/admin/import-revendications', name: 'admin_revendication_import', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, RevendicationImporter $importer): Response
    {
        $result = null;
        $error = null;
        if ($request->isMethod('POST')) {
            $file = $request->files->get('csv');
            if (!$this->isCsrfTokenValid('rev_import', (string) $request->request->get('_token'))) {
                $error = 'Session expirée.';
            } elseif (!$file || !$file->isValid()) {
                $error = 'Choisissez un fichier CSV.';
            } else {
                try {
                    $result = $importer->import((string) file_get_contents($file->getPathname()));
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('admin/revendication_import.html.twig', ['result' => $result, 'error' => $error]);
    }
}
