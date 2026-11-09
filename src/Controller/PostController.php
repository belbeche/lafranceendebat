<?php
// src/Controller/PostController.php

namespace App\Controller;

use App\Entity\Post;
use App\Form\PostType;
use App\Entity\Comment;
use App\Form\CommentType;
use App\Repository\PostRepository;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

/**
 * Contrôleur HTTP "PostController".
 *
 * Routes :
 *  - GET /actualites (app_actualites)
 *  - GET /actualites/{id} (app_actualites_show)
 *  - GET /actualites/categorie/{type} (app_actualites_by_type)
 *  - GET /admin/posts (admin_posts_index)
 *  - GET /admin/posts/new (admin_posts_new)
 *  - GET /admin/posts/{id}/edit (admin_posts_edit)
 *  - POST /admin/posts/{id}/delete (admin_posts_delete)
 *  - POST /admin/posts/{id}/toggle (admin_posts_toggle)
 *  - POST /admin/posts/{id}/approve-comments (admin_posts_approve_comments)
 * Accès : ROLE_ADMIN.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
class PostController extends AbstractController
{
    #[Route('/actualites', name: 'app_actualites')]
    public function index(
        PostRepository $postRepository,
        Request $request,
        EntityManagerInterface $entityManager,
        FormFactoryInterface $formFactory
    ): Response {
        // Récupérer toutes les publications (publiées et masquées) avec pagination
        $page = $request->query->getInt('page', 1);
        $limit = 12; // Nombre de posts par page
        
        // Utiliser une requête optimisée pour récupérer toutes les publications
        $paginator = $postRepository->findAllPaginated($page, $limit);
        $totalPosts = count($paginator);
        $maxPages = ceil($totalPosts / $limit);

        // Créer un formulaire de commentaire générique
        $comment = new Comment();
        $commentForm = $formFactory->createNamed('comment_form', CommentType::class, $comment);
        $commentForm->handleRequest($request);

        // Gestion de la soumission du formulaire de commentaire
        if ($commentForm->isSubmitted() && $commentForm->isValid()) {
            // Vérifier que l'utilisateur est connecté
            if (!$this->getUser()) {
                $this->addFlash('error', 'Vous devez être connecté pour commenter.');
                return $this->redirectToRoute('app_login');
            }
            
            // Récupérer l'ID du post
            $postId = $request->request->get('post_id');
            
            if ($postId) {
                $post = $postRepository->find($postId);
                
                if ($post) {
                    // Sauvegarder le commentaire
                    $comment->setAuthor($this->getUser());
                    $comment->setPost($post);
                    
                    // Par défaut, les commentaires doivent être approuvés
                    $comment->setIsApproved(false);
                    
                    $entityManager->persist($comment);
                    $entityManager->flush();

                    $this->addFlash('success', 'Votre commentaire a été soumis et sera publié après modération.');
                    return $this->redirectToRoute('app_actualites', ['page' => $page]);
                }
            }
            
            $this->addFlash('error', 'Impossible de publier le commentaire.');
        }

        return $this->render('post/index.html.twig', [
            'posts' => $paginator,
            'commentForm' => $commentForm->createView(),
            'current_page' => $page,
            'max_pages' => $maxPages,
            'total_posts' => $totalPosts,
        ]);
    }

    #[Route('/actualites/{id}', name: 'app_actualites_show')]
    public function show(
        int $id,
        PostRepository $postRepository,
        CommentRepository $commentRepository,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Récupérer le post par son id
        $post = $postRepository->findOneBy(['id' => $id]);
        
        if (!$post || !$post->isPublished()) {
            throw $this->createNotFoundException('Cette publication n\'existe pas.');
        }

        // Incrémenter le compteur de vues (si vous avez cette propriété)
        if (method_exists($post, 'incrementViews')) {
            $post->incrementViews();
            $entityManager->flush();
        }

        // Récupérer les commentaires approuvés avec pagination
        $page = $request->query->getInt('page', 1);
        $commentsPaginator = $commentRepository->findApprovedCommentsPaginated($post->getId(), $page);
        $totalComments = count($commentsPaginator);
        $maxPages = ceil($totalComments / 10);

        // Créer un formulaire de commentaire
        $comment = new Comment();
        $commentForm = $this->createForm(CommentType::class, $comment);
        $commentForm->handleRequest($request);

        if ($commentForm->isSubmitted() && $commentForm->isValid()) {
            if (!$this->getUser()) {
                $this->addFlash('error', 'Vous devez être connecté pour commenter.');
                return $this->redirectToRoute('app_login');
            }
            
            $comment->setAuthor($this->getUser());
            $comment->setPost($post);
            
            // Par défaut, les commentaires doivent être approuvés par un admin
            $comment->setIsApproved(false);
            
            $entityManager->persist($comment);
            $entityManager->flush();

            $this->addFlash('success', 'Votre commentaire a été soumis et sera publié après modération.');
            return $this->redirectToRoute('app_actualites_show', ['slug' => $post->getSlug()]);
        }

        // Récupérer les publications similaires (par catégorie/tags)
        $relatedPosts = $postRepository->findRelatedPosts($post, 3);

        return $this->render('post/show.html.twig', [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
            'commentForm' => $commentForm->createView(),
            'comments' => $commentsPaginator,
            'current_page' => $page,
            'max_pages' => $maxPages,
            'total_comments' => $totalComments,
        ]);
    }

    #[Route('/actualites/categorie/{type}', name: 'app_actualites_by_type')]
    public function byType(
        string $type, 
        PostRepository $postRepository,
        Request $request
    ): Response {
        $types = ['video', 'image', 'document', 'text'];
        
        if (!in_array($type, $types)) {
            throw $this->createNotFoundException('Catégorie non trouvée.');
        }

        $page = $request->query->getInt('page', 1);
        $limit = 12;
        
        $paginator = $postRepository->findByTypePaginated($type, $page, $limit);
        $totalPosts = count($paginator);
        $maxPages = ceil($totalPosts / $limit);

        return $this->render('post/by_type.html.twig', [
            'posts' => $paginator,
            'currentType' => $type,
            'current_page' => $page,
            'max_pages' => $maxPages,
            'total_posts' => $totalPosts,
        ]);
    }

    // === PARTIE ADMINISTRATION ===

    #[Route('/admin/posts', name: 'admin_posts_index')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminIndex(
        PostRepository $postRepository,
        Request $request
    ): Response {
        $page = $request->query->getInt('page', 1);
        $status = $request->query->get('status', 'all'); // all, published, draft
        $limit = 20;
        
        $posts = $postRepository->findAllPaginated($page, $limit, $status);
        $totalPosts = count($posts);
        $maxPages = ceil($totalPosts / $limit);

        return $this->render('admin/post/index.html.twig', [
            'posts' => $posts,
            'current_page' => $page,
            'max_pages' => $maxPages,
            'total_posts' => $totalPosts,
            'current_status' => $status,
        ]);
    }

    #[Route('/admin/posts/new', name: 'admin_posts_new')]
    #[IsGranted('ROLE_ADMIN')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        LoggerInterface $logger
    ): Response {
        $post = new Post();
        $post->setAuthor($this->getUser());
        
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Générer un slug à partir du titre
            $title = $post->getTitle();
            if ($title) {
                $slug = $slugger->slug(strtolower($title));
                $post->setSlug($slug); // Correction: setSlug au lieu de setTitle
            }

            // Gestion de l'upload du fichier
            $mediaFile = $form->get('mediaFile')->getData();
            
            if ($mediaFile) {
                $originalFilename = pathinfo($mediaFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$mediaFile->guessExtension();

                try {
                    $mediaFile->move(
                        $this->getParameter('posts_media_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // 16/09 (Claude) : on loggue la vraie cause (avant on l'avalait silencieusement,
                    // ce qui rendait le bug invisible pour l'admin). Le message affiche reste generique
                    // cote utilisateur mais le detail est dans les logs pour diagnostiquer (ex: droits
                    // d'ecriture Windows/OneDrive sur public/uploads/posts).
                    $logger->error('Echec upload media post (new): {message}', ['message' => $e->getMessage()]);
                    $this->addFlash('error', 'Erreur lors de l\'upload du fichier : le serveur n\'a pas pu ecrire le fichier sur le disque. Verifiez les droits du dossier public/uploads/posts.');
                    return $this->redirectToRoute('admin_posts_new');
                }

                $post->setMediaPath($newFilename);
                
                // Déterminer le type de média
                $mimeType = $mediaFile->getClientMimeType();
                if (str_starts_with($mimeType, 'image/')) {
                    $post->setMediaType('image');
                } elseif (str_starts_with($mimeType, 'video/')) {
                    $post->setMediaType('video');
                } elseif (str_starts_with($mimeType, 'application/pdf')) {
                    $post->setMediaType('document');
                } else {
                    $post->setMediaType('file');
                }
            }

            $entityManager->persist($post);
            $entityManager->flush();

            $this->addFlash('success', 'Publication créée avec succès !');
            return $this->redirectToRoute('admin_posts_index');
        }

        return $this->render('admin/post/new.html.twig', [
            'form' => $form->createView(),
            'post' => $post,
        ]);
    }

    #[Route('/admin/posts/{id}/edit', name: 'admin_posts_edit')]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        LoggerInterface $logger
    ): Response {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Mettre à jour le slug si le titre a changé
            $title = $post->getTitle();
            if ($title) {
                $slug = $slugger->slug(strtolower($title));
                $post->setSlug($slug); // Correction: setSlug au lieu de setTitle
            }

            $mediaFile = $form->get('mediaFile')->getData();
            
            if ($mediaFile) {
                // Supprimer l'ancien fichier s'il existe
                if ($post->getMediaPath()) {
                    $oldFilePath = $this->getParameter('posts_media_directory').'/'.$post->getMediaPath();
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                // Upload du nouveau fichier
                $originalFilename = pathinfo($mediaFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$mediaFile->guessExtension();

                try {
                    $mediaFile->move(
                        $this->getParameter('posts_media_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // 16/09 (Claude) : meme correctif que new() - logguer la vraie cause au lieu de l'avaler.
                    $logger->error('Echec upload media post (edit #{id}): {message}', ['id' => $post->getId(), 'message' => $e->getMessage()]);
                    $this->addFlash('error', 'Erreur lors de l\'upload du fichier : le serveur n\'a pas pu ecrire le fichier sur le disque. Verifiez les droits du dossier public/uploads/posts.');
                    return $this->redirectToRoute('admin_posts_edit', ['id' => $post->getId()]);
                }

                $post->setMediaPath($newFilename);
                
                // Mettre à jour le type de média
                $mimeType = $mediaFile->getClientMimeType();
                if (str_starts_with($mimeType, 'image/')) {
                    $post->setMediaType('image');
                } elseif (str_starts_with($mimeType, 'video/')) {
                    $post->setMediaType('video');
                } elseif (str_starts_with($mimeType, 'application/pdf')) {
                    $post->setMediaType('document');
                } else {
                    $post->setMediaType('file');
                }
            }

            $post->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Publication modifiée avec succès !');
            return $this->redirectToRoute('admin_posts_index');
        }

        return $this->render('admin/post/edit.html.twig', [
            'form' => $form->createView(),
            'post' => $post,
        ]);
    }

    #[Route('/admin/posts/{id}/delete', name: 'admin_posts_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager
    ): Response {
        $csrfToken = $request->request->get('_token');
        if (!$csrfToken || !$this->isCsrfTokenValid('delete'.$post->getId(), $csrfToken)) {
            $this->addFlash('error', 'Token CSRF invalide ou manquant.');
            return $this->redirectToRoute('admin_posts_index');
        }

        // Supprimer le fichier associé
        if ($post->getMediaPath()) {
            $filePath = $this->getParameter('posts_media_directory').'/'.$post->getMediaPath();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // Supprimer les commentaires associés
        $comments = $post->getComments();
        foreach ($comments as $comment) {
            $entityManager->remove($comment);
        }

        $entityManager->remove($post);
        $entityManager->flush();

        $this->addFlash('success', 'Publication et ses commentaires supprimés avec succès !');
        return $this->redirectToRoute('admin_posts_index');
    }

    #[Route('/admin/posts/{id}/toggle', name: 'admin_posts_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggle(
        Request $request,
        Post $post, 
        EntityManagerInterface $entityManager
    ): Response {
        $csrfToken = $request->request->get('_token');
        if (!$csrfToken || !$this->isCsrfTokenValid('toggle'.$post->getId(), $csrfToken)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_posts_index');
        }
        
        $post->setIsPublished(!$post->isPublished());
        $entityManager->flush();

        $status = $post->isPublished() ? 'publiée' : 'masquée';
        $this->addFlash('success', "Publication {$status} avec succès !");

        return $this->redirectToRoute('admin_posts_index');
    }

    #[Route('/admin/posts/{id}/approve-comments', name: 'admin_posts_approve_comments', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function approveComments(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager
    ): Response {
        $csrfToken = $request->request->get('_token');
        if (!$csrfToken || !$this->isCsrfTokenValid('approve_comments'.$post->getId(), $csrfToken)) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_posts_index');
        }

        $approvedCount = 0;
        foreach ($post->getComments() as $comment) {
            if (!$comment->isApproved()) {
                $comment->setIsApproved(true);
                $approvedCount++;
            }
        }

        if ($approvedCount > 0) {
            $entityManager->flush();
            $this->addFlash('success', "{$approvedCount} commentaire(s) approuvé(s) !");
        } else {
            $this->addFlash('info', 'Aucun commentaire à approuver.');
        }

        return $this->redirectToRoute('admin_posts_index');
    }
}