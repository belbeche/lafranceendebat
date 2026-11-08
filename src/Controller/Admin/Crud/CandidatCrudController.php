<?php

namespace App\Controller\Admin\Crud;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Candidat;
use App\Form\Admin\CandidatAdminType;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP « CandidatCrudController ».
 *
 * Routes :
 *  - GET /admin/candidats (admin_candidat_)
 * Accès : ROLE_ADMIN.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/candidats', name: 'admin_candidat_')]
class CandidatCrudController extends AbstractCrudController
{
    protected function entityClass(): string { return Candidat::class; }
    protected function formClass(): string { return CandidatAdminType::class; }
    protected function routePrefix(): string { return 'admin_candidat_'; }
    protected function title(): string { return 'Candidats et élus'; }
    protected function orderBy(): array { return ['name' => 'ASC']; }
    protected function columns(): array
    {
        return ['Nom' => 'name', 'Étiquette déclarée' => 'declaredLabel', 'Publié' => 'isPublished', 'Débat public accepté' => 'acceptsPublicDebate'];
    }
}
