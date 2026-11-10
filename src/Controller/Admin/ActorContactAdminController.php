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
 * Contrôleur HTTP "ActorContactAdminController".
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
    private const FILTER_KEYS = ['q', 'category', 'dept', 'city', 'status', 'invited', 'consent', 'validated', 'phone', 'website', 'address'];

    /** @return array<string,string> filtres nettoyés depuis la requête */
    private function filters(Request $request): array
    {
        $f = [];
        foreach (self::FILTER_KEYS as $k) {
            $f[$k] = trim((string) $request->query->get($k, ''));
        }
        if (!isset(ActorContact::CATEGORIES[$f['category']])) { $f['category'] = ''; }
        if (!in_array($f['status'], [ActorContact::ACTIVE, ActorContact::UNSUBSCRIBED, ActorContact::BOUNCED, ActorContact::COMPLAINT], true)) { $f['status'] = ''; }
        if (!preg_match('/^(\d{2,3}|2[AB])$/i', $f['dept'])) { $f['dept'] = ''; }
        foreach (['invited', 'consent', 'validated', 'phone', 'website', 'address'] as $k) {
            if (!in_array($f[$k], ['oui', 'non'], true)) { $f[$k] = ''; }
        }

        return $f;
    }

    /** Page d'ensemble façon "statistiques des établissements" : chiffres clés, répartition, filtres, liste paginée. */
    #[Route('', name: 'admin_actors', methods: ['GET'])]
    public function index(Request $request, ActorContactRepository $repo): Response
    {
        return $this->view($request, $repo, []);
    }

    /** @param array<string,mixed> $extra result / error après un import */
    private function view(Request $request, ActorContactRepository $repo, array $extra): Response
    {
        $f = $this->filters($request);
        $page = max(1, (int) $request->query->get('page', 1));
        $per = 50;
        $qb = $repo->filtered($f);
        $total = (int) (clone $qb)->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
        $rows = $qb->orderBy('a.id', 'DESC')->setFirstResult(($page - 1) * $per)->setMaxResults($per)->getQuery()->getResult();

        return $this->render('admin/actors/index.html.twig', $extra + [
            'counts' => $repo->countsByStatus(),
            'eligible' => $repo->countEligible(true), 'consented' => $repo->countConsented(),
            'overview' => $repo->overview(), 'byCategory' => $repo->byCategory(), 'byDept' => $repo->byDepartment(),
            'cities' => $f['dept'] ? $repo->citiesOf($f['dept']) : [],
            'f' => $f, 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $per)),
            'min_days' => ActorContactRepository::MIN_DAYS_BETWEEN_CONTACTS,
            'result' => null, 'error' => null,
        ]);
    }

    /** Export de la sélection filtrée : CSV (point-virgule, UTF-8 avec BOM) qui s'ouvre directement dans Excel. */
    #[Route('/export.csv', name: 'admin_actors_export', methods: ['GET'])]
    public function export(Request $request, ActorContactRepository $repo): Response
    {
        $f = $this->filters($request);
        $rows = $repo->filtered($f)->orderBy('a.id', 'ASC')->setMaxResults(50000)->getQuery()->toIterable();
        $resp = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($rows): void {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, ['organisation', 'fonction', 'categorie', 'email', 'telephone', 'adresse', 'code_postal', 'commune', 'site', 'statut', 'invite_le', 'accord', 'source'], ';');
            foreach ($rows as $a) {
                /** @var ActorContact $a */
                fputcsv($o, [$a->getOrganisation(), $a->getRole(), $a->getCategoryLabel(), $a->getEmail(), $a->getPhone(), $a->getAddress(), $a->getPostalCode(), $a->getCity(), $a->getWebsite(), $a->getStatus(), $a->getInvitedAt()?->format('Y-m-d'), $a->getConsentAt() ? 'oui' : 'non', $a->getSourceUrl()], ';');
            }
            fclose($o);
        });
        $resp->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $resp->headers->set('Content-Disposition', 'attachment; filename="acteurs-publics-' . date('Y-m-d') . '.csv"');

        return $resp;
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

        return $this->view($request, $repo, ['result' => $result, 'error' => $error]);
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

    /**
     * Validation par l'equipe : "cochees" (ids[]) ou "toute la selection filtree" (memes filtres que la liste).
     * Seules les fiches actives sont concernees. Un contact desinscrit ne devient jamais eligible.
     */
    #[Route('/validation', name: 'admin_actors_validate', methods: ['POST'])]
    public function validateSelection(Request $request, ActorContactRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('actors_validate', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->redirectToRoute('admin_actors');
        }
        $mode = (string) $request->request->get('mode');
        $unvalidate = 'retirer' === $mode;
        if (!in_array($mode, ['cochees', 'filtre', 'retirer'], true)) {
            return $this->redirectToRoute('admin_actors');
        }
        if ('filtre' === $mode) {
            if (!$request->request->getBoolean('controle')) {
                $this->addFlash('danger', 'Cochez la case de contrôle : vous confirmez avoir relu les fiches (adresse professionnelle publiée, source ouverte).');

                return $this->redirectToRoute('admin_actors', array_filter($this->filtersFrom($request->request->all('f'))));
            }
            $ids = array_map('intval', array_column((clone $repo->filtered($this->filtersFrom($request->request->all('f'))))->select('a.id')->andWhere('a.status = :act')->setParameter('act', ActorContact::ACTIVE)->getQuery()->getScalarResult(), 'id'));
        } else {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->request->all('ids')), static fn (int $i): bool => $i > 0)));
        }
        $by = $this->getUser() instanceof \App\Entity\User ? $this->getUser()->getUserIdentifier() : 'admin';
        $done = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach ($repo->findBy(['id' => $chunk]) as $a) {
                if ($unvalidate) {
                    if ($a->isValidated()) { $a->unvalidate(); ++$done; }
                } elseif (ActorContact::ACTIVE === $a->getStatus() && !$a->isValidated()) {
                    $a->validate($by); ++$done;
                }
            }
            $em->flush();
            $em->clear();
        }
        $this->addFlash('success', $unvalidate ? sprintf('%d fiche(s) repassée(s) à valider.', $done) : sprintf('%d fiche(s) validée(s). Seules les fiches validées peuvent recevoir l\'invitation. Rien n\'est envoyé tant que vous ne lancez pas un envoi.', $done));

        return $this->redirectToRoute('admin_actors', array_filter($this->filtersFrom($request->request->all('f'))));
    }

    /** @param array<string,mixed> $raw @return array<string,string> */
    private function filtersFrom(array $raw): array
    {
        $req = new Request(array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $raw));

        return $this->filters($req);
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
