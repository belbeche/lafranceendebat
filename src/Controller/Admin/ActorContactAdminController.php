<?php

namespace App\Controller\Admin;

use App\Entity\ActorContact;
use App\Repository\ActorContactRepository;
use App\Service\ActorImporter;
use App\Service\PublicActorImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur HTTP « ActorContactAdminController ».
 *
 * Routes :
 *  - GET /admin/acteurs-publics (admin_actors)
 *  - POST /admin/acteurs-publics/importer (admin_actors_import)
 *  - POST /admin/acteurs-publics/importer-auto (admin_actors_import_auto)
 *  - POST /admin/acteurs-publics/{id<\d+>}/exclure (admin_actor_exclude)
 * Accès : IS_AUTHENTICATED_FULLY, ROLE_ADMIN.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
#[Route('/admin/acteurs-publics')]
#[IsGranted('ROLE_ADMIN')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ActorContactAdminController extends AbstractController
{
    #[Route('', name: 'admin_actors', methods: ['GET'])]
    public function index(ActorContactRepository $repo): Response
    {
        return $this->render('admin/actors/index.html.twig', [
            'counts' => $repo->countsByStatus(),
            'eligible' => $repo->countEligible(true),'consented' => $repo->countConsented(),
            'recent' => $repo->findBy([], ['id' => 'DESC'], 50),
            'min_days' => ActorContactRepository::MIN_DAYS_BETWEEN_CONTACTS,
            'result' => null,
        ]);
    }

    #[Route('/importer', name: 'admin_actors_import', methods: ['POST'])]
    public function import(Request $request, ActorImporter $importer, ActorContactRepository $repo): Response
    {
        if (!$this->isCsrfTokenValid('actors_import', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $result = null;
        $error = null;
        $file = $request->files->get('csv');
        if (!$request->request->getBoolean('attest')) {
            $error = 'Vous devez certifier l\'origine et la nature des adresses.';
        } elseif (!$file || !$file->isValid() || $file->getSize() > ActorImporter::MAX_BYTES) {
            $error = 'Fichier CSV manquant ou supérieur à 1 Mo.';
        } elseif (!in_array(strtolower((string) $file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            $error = 'Format accepté : .csv.';
        } else {
            try {
                $result = $importer->import($file->getPathname());
            } catch (\Throwable $e) {
                $error = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Import impossible : fichier illisible.';
            }
        }

        return $this->render('admin/actors/index.html.twig', [
            'counts' => $repo->countsByStatus(), 'eligible' => $repo->countEligible(true),'consented' => $repo->countConsented(), 'recent' => $repo->findBy([], ['id' => 'DESC'], 50),
            'min_days' => ActorContactRepository::MIN_DAYS_BETWEEN_CONTACTS, 'result' => $result, 'error' => $error,
        ]);
    }

    #[Route('/importer-auto', name: 'admin_actors_import_auto', methods: ['POST'])]
    public function importAuto(Request $request, PublicActorImporter $auto): Response
    {
        if (!$this->isCsrfTokenValid('actors_auto', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $source = (string) $request->request->get('source');
        if (!in_array($source, PublicActorImporter::SOURCES, true)) {
            $this->addFlash('danger', 'Source inconnue.');

            return $this->redirectToRoute('admin_actors');
        }
        $dry = 'simuler' === $request->request->get('mode');
        @set_time_limit(280);
        $r = $auto->run($source, $dry, ['recherche' => $request->request->get('recherche', 'Mairie'), 'departement' => trim((string) $request->request->get('departement')) ?: null, 'limite' => 1000]);
        if ($r['error']) {
            $this->addFlash('danger', $r['error']);
        } else {
            $this->addFlash('success', sprintf('%s : %d lues, %d ajoutées, %d déjà connues, %d refusées%s. Rien n\'est envoyé : validez avant tout message.', $source, $r['read'], $r['added'], $r['skipped'], $r['refused'], $dry ? ' (simulation, rien enregistré)' : ''));
        }

        return $this->redirectToRoute('admin_actors');
    }

    #[Route('/{id<\d+>}/exclure', name: 'admin_actor_exclude', methods: ['POST'])]
    public function exclude(ActorContact $a, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('actor_exclude' . $a->getId(), (string) $request->request->get('_token'))) {
            $a->unsubscribe();
            $em->flush();
            $this->addFlash('success', 'Contact exclu définitivement (liste de suppression).');
        }

        return $this->redirectToRoute('admin_actors');
    }
}
