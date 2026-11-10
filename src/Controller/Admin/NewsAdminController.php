<?php

namespace App\Controller\Admin;

use App\Entity\NewsItem;
use App\Service\Content\NewsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des actualités sans toucher au code :
 *  - GET  /admin/actualites                 liste, zone d'import (texte collé ou fichier JSON)
 *  - GET|POST /admin/actualites/nouvelle    écrire une actualité
 *  - GET|POST /admin/actualites/{id}        modifier
 *  - POST /admin/actualites/importer        texte collé -> brouillon ; fichier .json -> brouillons (ou publiées avec "publier")
 *  - POST /admin/actualites/{id}/publier    publier / retirer (la publication exige une source https)
 *  - POST /admin/actualites/{id}/supprimer
 * Chaque action POST exige un jeton CSRF ("news").
 */
#[Route('/admin/actualites')]
#[IsGranted('ROLE_ADMIN')]
class NewsAdminController extends AbstractController
{
    public function __construct(private readonly \App\Service\RevisionRecorder $revisions, private readonly \App\Service\ModerationService $reports)
    {
    }

    #[Route('', name: 'admin_news', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('admin/news/index.html.twig', ['items' => $em->getRepository(NewsItem::class)->findBy([], ['publishedOn' => 'DESC', 'id' => 'DESC'], 200)]);
    }

    #[Route('/nouvelle', name: 'admin_news_new', methods: ['GET', 'POST'], priority: 5)]
    public function create(Request $request, EntityManagerInterface $em, NewsService $news): Response
    {
        return $this->form(new NewsItem(), $request, $em, $news, true);
    }

    #[Route('/{id<\d+>}', name: 'admin_news_edit', methods: ['GET', 'POST'])]
    public function edit(NewsItem $n, Request $request, EntityManagerInterface $em, NewsService $news): Response
    {
        return $this->form($n, $request, $em, $news, false);
    }

    #[Route('/importer', name: 'admin_news_import', methods: ['POST'], priority: 5)]
    public function import(Request $request, EntityManagerInterface $em, NewsService $news): Response
    {
        $this->csrf($request);
        $file = $request->files->get('fichier');
        if ($file && $file->isValid()) {
            if ($file->getSize() > 1_000_000) {
                $this->addFlash('danger', 'Fichier trop gros (1 Mo maximum).');

                return $this->redirectToRoute('admin_news');
            }
            $r = $news->importJson((string) file_get_contents($file->getPathname()), $request->request->getBoolean('publier'));
            $this->addFlash($r['created'] ? 'success' : 'warning', sprintf('%d actualité(s) importée(s), %d déjà présente(s).', $r['created'], $r['skipped']));
            foreach (array_slice($r['errors'], 0, 5) as $e) {
                $this->addFlash('warning', $e);
            }

            return $this->redirectToRoute('admin_news');
        }
        $text = trim((string) $request->request->get('texte'));
        if (mb_strlen($text) < 40) {
            $this->addFlash('danger', 'Collez le texte de l\'actualité (40 caractères minimum) ou choisissez un fichier JSON.');

            return $this->redirectToRoute('admin_news');
        }
        $on = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->request->get('date')) ?: null;
        $n = $news->fromText($text, (string) $request->request->get('source_nom'), (string) $request->request->get('source_url') ?: null, $on);
        $em->persist($n);
        $em->flush();
        $this->addFlash('success', 'Brouillon créé. Relisez-le, complétez la source puis publiez.');

        return $this->redirectToRoute('admin_news_edit', ['id' => $n->getId()]);
    }

    #[Route('/{id<\d+>}/publier', name: 'admin_news_toggle', methods: ['POST'])]
    public function toggle(NewsItem $n, Request $request, EntityManagerInterface $em): Response
    {
        $this->csrf($request);
        if ($n->isPublished()) {
            $n->unpublish();
            $this->addFlash('success', 'Actualité retirée de la publication.');
        } elseif ($problem = $n->publish()) {
            $this->addFlash('danger', 'Pas publiée : ' . $problem);

            return $this->redirectToRoute('admin_news_edit', ['id' => $n->getId()]);
        } else {
            $this->addFlash('success', 'Actualité publiée.');
            $this->reports->dismissReportsFor('actualite', (int) $n->getId());
        }
        $em->flush();

        return $this->redirectToRoute('admin_news');
    }

    #[Route('/{id<\d+>}/supprimer', name: 'admin_news_delete', methods: ['POST'])]
    public function delete(NewsItem $n, Request $request, EntityManagerInterface $em): Response
    {
        $this->csrf($request);
        $em->remove($n);
        $em->flush();
        $this->addFlash('success', 'Actualité supprimée.');

        return $this->redirectToRoute('admin_news');
    }

    private function form(NewsItem $n, Request $request, EntityManagerInterface $em, NewsService $news, bool $isNew): Response
    {
        $errors = [];
        if ($request->isMethod('POST')) {
            $this->csrf($request);
            // Version avant modification, gardee si l'actualite est deja publiee (historique public, cf. /notre-modele).
            $before = $isNew ? [] : ['title' => $n->getTitle(), 'summary' => $n->getSummary(), 'body' => (string) $n->getBody(), 'sourceName' => $n->getSourceName(), 'sourceUrl' => (string) $n->getSourceUrl()];
            $wasPublished = !$isNew && $n->isPublished();
            $n->setTitle((string) $request->request->get('title'))->setSummary((string) $request->request->get('summary'))->setBody((string) $request->request->get('body'))
                ->setSourceName((string) $request->request->get('source_name'))->setSourceUrl((string) $request->request->get('source_url'));
            if ($dt = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->request->get('date'))) {
                $n->setPublishedOn($dt);
            }
            $rawUrl = trim((string) $request->request->get('source_url'));
            if ('' !== $rawUrl && !$n->getSourceUrl()) {
                $errors[] = 'Le lien de la source doit commencer par https://';
            }
            if (mb_strlen($n->getTitle()) < 8) {
                $errors[] = 'Titre : 8 caractères minimum.';
            }
            if (mb_strlen($n->getSummary()) < 40) {
                $errors[] = 'Résumé : 40 caractères minimum.';
            }
            if (!$errors) {
                if ($isNew || '' === $n->getSlug()) {
                    $n->setSlug($news->uniqueSlug($n->getTitle()));
                }
                if ($wasPublished) {
                    $this->revisions->record('actualite', (int) $n->getId(), $before, new \DateTimeImmutable('-1 day'), true, ['title' => $n->getTitle(), 'summary' => $n->getSummary(), 'body' => (string) $n->getBody(), 'sourceName' => $n->getSourceName(), 'sourceUrl' => (string) $n->getSourceUrl()]);
                }
                $em->persist($n);
                $em->flush();
                if ($request->request->getBoolean('publier') && ($p = $n->publish())) {
                    $this->addFlash('warning', 'Enregistrée en brouillon : ' . $p);
                } elseif ($request->request->getBoolean('publier')) {
                    $em->flush();
                    $this->reports->dismissReportsFor('actualite', (int) $n->getId());
                    $this->addFlash('success', 'Actualité publiée.');
                } else {
                    $this->addFlash('success', 'Actualité enregistrée.');
                }

                return $this->redirectToRoute('admin_news_edit', ['id' => $n->getId()]);
            }
        }

        return $this->render('admin/news/form.html.twig', ['n' => $n, 'errors' => $errors, 'isNew' => $isNew]);
    }

    private function csrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('news', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
