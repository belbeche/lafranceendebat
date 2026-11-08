<?php

namespace App\Controller\Admin\Crud;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * CRUD d'administration minimal et uniforme. Chaque sous-classe declare son entite, son formulaire
 * et ses colonnes, et fixe son prefixe de routes via #[Route('/admin/...', name: 'admin_xxx_')].
 */
#[IsGranted('ROLE_ADMIN')]
abstract class AbstractCrudController extends AbstractController
{
    /** @return class-string */
    abstract protected function entityClass(): string;

    /** @return class-string */
    abstract protected function formClass(): string;

    /** Prefixe de nom de route, ex. "admin_debat_". */
    abstract protected function routePrefix(): string;

    abstract protected function title(): string;

    /** @return array<string, string> libelle de colonne => propriete */
    abstract protected function columns(): array;

    /** @return array<string, string> */
    protected function orderBy(): array
    {
        return ['id' => 'DESC'];
    }

    protected function createEntity(): object
    {
        $class = $this->entityClass();

        return new $class();
    }

    protected function afterSave(object $entity): void
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('admin/crud/index.html.twig', [
            'title' => $this->title(),
            'prefix' => $this->routePrefix(),
            'columns' => $this->columns(),
            'rows' => $em->getRepository($this->entityClass())->findBy([], $this->orderBy(), 300),
        ]);
    }

    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $entity = $this->createEntity();
        $form = $this->createForm($this->formClass(), $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entity);
            $this->afterSave($entity);
            $em->flush();
            $this->addFlash('success', 'Élément créé.');

            return $this->redirectToRoute($this->routePrefix() . 'index');
        }

        return $this->renderCrudForm($form, 'Nouveau', $entity, $form->isSubmitted());
    }

    #[Route('/{id}/modifier', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $entity = $em->find($this->entityClass(), $id) ?? throw $this->createNotFoundException();
        $form = $this->createForm($this->formClass(), $entity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->afterSave($entity);
            $em->flush();
            $this->addFlash('success', 'Modifications enregistrées.');

            return $this->redirectToRoute($this->routePrefix() . 'index');
        }

        return $this->renderCrudForm($form, 'Modifier', $entity, $form->isSubmitted());
    }

    #[Route('/{id}/supprimer', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $entity = $em->find($this->entityClass(), $id) ?? throw $this->createNotFoundException();
        if ($this->isCsrfTokenValid('delete' . $this->routePrefix() . $id, (string) $request->request->get('_token'))) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', 'Élément supprimé.');
        } else {
            $this->addFlash('danger', 'Session expirée. Réessayez.');
        }

        return $this->redirectToRoute($this->routePrefix() . 'index');
    }

    private function renderCrudForm($form, string $action, object $entity, bool $submitted): Response
    {
        return $this->render('admin/crud/form.html.twig', [
            'title' => $this->title(),
            'action' => $action,
            'prefix' => $this->routePrefix(),
            'form' => $form,
        ], new Response(status: $submitted ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
