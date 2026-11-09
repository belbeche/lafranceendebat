<?php
// src/Controller/PostController.php

namespace App\Controller\Admin;

use Symfony\Component\Security\Http\Attribute\IsGranted;
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

/**
 * Contrôleur HTTP "PostController".
 *
 * Routes :
 *  - GET /admin/posts (admin_posts_index)
 *  - GET /admin/posts/new (admin_posts_new)
 *  - GET /admin/posts/{id}/edit (admin_posts_edit)
 *  - GET /admin/posts/{id}/delete (admin_posts_delete)
 *  - POST /admin/posts/{id}/toggle (admin_posts_toggle)
 * Accès : ROLE_ADMIN.
 */
#[IsGranted('ROLE_ADMIN')]
class PostController extends AbstractController
{
    #[Route('/admin/posts', name: 'admin_posts_index')]
    public function adminIndex(
        PostRepository $postRepository, 
        FormFactoryInterface $formFactory
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Récupérer les posts
        $posts = $postRepository->findBy([], ['createdAt' => 'DESC']);
        
        // Créer la variable postsWithForms
        $postsWithForms = [];
        foreach ($posts as $post) {
            $commentForm = $formFactory->create(CommentType::class);
            $postsWithForms[] = [
                'post' => $post,
                'commentForm' => $commentForm->createView()
            ];
        }

        // Créer aussi un formulaire séparé si besoin
        $commentForm = $formFactory->create(CommentType::class);

        return $this->render('admin/post/index.html.twig', [
            'posts' => $postRepository->findBy([], ['createdAt' => 'DESC']),
            'comments' => $postRepository->findRecentComments(10),
            'total_posts' => $postRepository->count([]),
            'published_posts' => $postRepository->count(['isPublished' => true]),
            'unpublished_posts' => $postRepository->count(['isPublished' => false]),
            'posts' => $postRepository->findBy([], ['createdAt' => 'DESC']),
            'postsWithForms' => $postsWithForms,
            'commentForm' => $commentForm->createView(),
        ]);
    }

    #[Route('/admin/posts/new', name: 'admin_posts_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $post = new Post();
        $post->setAuthor($this->getUser());
        
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'upload du fichier
            $mediaFile = $form->get('mediaFile')->getData();
            
            if ($mediaFile) {
                $originalFilename = pathinfo($mediaFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$mediaFile->guessExtension();

                // Déplacer le fichier
                try {
                    $mediaFile->move(
                        $this->getParameter('posts_media_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload du fichier.');
                }

                $post->setMediaPath($newFilename);
                $post->setMediaType($mediaFile->getClientMimeType());
            }

            $entityManager->persist($post);
            $entityManager->flush();

            $this->addFlash('success', 'Publication créée avec succès !');
            return $this->redirectToRoute('admin_posts_index');
        }

        return $this->render('admin/post/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/posts/{id}/edit', name: 'admin_posts_edit')]
    public function edit(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mediaFile = $form->get('mediaFile')->getData();
            
            if ($mediaFile) {
                // Supprimer l'ancien fichier
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
                    $this->addFlash('error', 'Erreur lors de l\'upload du fichier.');
                }

                $post->setMediaPath($newFilename);
                $post->setMediaType($mediaFile->getClientMimeType());
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

    #[Route('/admin/posts/{id}/delete', name: 'admin_posts_delete')]
    public function delete(
        Post $post,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Supprimer le fichier associé
        if ($post->getMediaPath()) {
            $filePath = $this->getParameter('posts_media_directory').'/'.$post->getMediaPath();
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $entityManager->remove($post);
        $entityManager->flush();

        $this->addFlash('success', 'Publication supprimée avec succès !');
        return $this->redirectToRoute('admin_posts_index');
    }

    #[Route('/admin/posts/{id}/toggle', name: 'admin_posts_toggle', methods: ['POST'])]
    public function toggle(Post $post, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        $post->setIsPublished(!$post->isPublished());
        $entityManager->flush();

        $status = $post->isPublished() ? 'publiée' : 'masquée';
        $this->addFlash('success', "Publication {$status} avec succès !");

        return $this->redirectToRoute('admin_posts_index');
    }
}