<?php

namespace App\Controller\Admin;

use App\Service\Agenda\EventVerifier;
use App\Service\Agenda\NationalEventImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur HTTP « AgendaImportController ».
 *
 * Routes :
 *  - GET /admin/agenda-import (admin_agenda_import)
 *  - POST /admin/agenda-import/lancer (admin_agenda_import_run)
 * Accès : ROLE_ADMIN.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
#[Route('/admin/agenda-import')]
#[IsGranted('ROLE_ADMIN')]
class AgendaImportController extends AbstractController
{
    #[Route('', name: 'admin_agenda_import', methods: ['GET'])]
    public function index(NationalEventImporter $importer): Response
    {
        return $this->render('admin/agenda/import.html.twig', ['report' => $importer->lastReport(), 'hosts' => EventVerifier::ALLOWED_HOSTS]);
    }

    #[Route('/lancer', name: 'admin_agenda_import_run', methods: ['POST'])]
    public function run(Request $request, NationalEventImporter $importer): Response
    {
        if ($this->isCsrfTokenValid('agenda_import', (string) $request->request->get('_token'))) {
            $r = $importer->run(false, 2);
            $this->addFlash($r['errors'] ? 'warning' : 'success', sprintf('Import terminé : %d créés, %d mis à jour, %d refusés.%s', $r['created'], $r['updated'], count($r['rejected']), $r['errors'] ? ' Une source était indisponible, voir le détail.' : ''));
        } else {
            $this->addFlash('danger', 'Session expirée.');
        }

        return $this->redirectToRoute('admin_agenda_import');
    }
}
