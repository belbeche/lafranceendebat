<?php

namespace App\Controller\Admin\Crud;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Revendication;
use App\Form\Admin\RevendicationAdminType;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP « RevendicationCrudController ».
 *
 * Routes :
 *  - GET /admin/revendications (admin_revendication_)
 * Accès : ROLE_ADMIN.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/revendications', name: 'admin_revendication_')]
class RevendicationCrudController extends AbstractCrudController
{
    protected function entityClass(): string { return Revendication::class; }
    protected function formClass(): string { return RevendicationAdminType::class; }
    protected function routePrefix(): string { return 'admin_revendication_'; }
    protected function title(): string { return 'Registre des revendications'; }
    protected function orderBy(): array { return ['updatedAt' => 'DESC']; }
    protected function columns(): array
    {
        return ['Revendication' => 'title', 'Portée par' => 'groupLabel', 'Thème' => 'theme', 'Statut' => 'statusLabel', 'Publiée' => 'isPublished'];
    }
}
