<?php

namespace App\Controller\Admin\Crud;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Debat;
use App\Form\Admin\DebatAdminType;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP « DebatCrudController ».
 *
 * Routes :
 *  - GET /admin/debats (admin_debat_)
 * Accès : ROLE_ADMIN.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/debats', name: 'admin_debat_')]
class DebatCrudController extends AbstractCrudController
{
    protected function entityClass(): string { return Debat::class; }
    protected function formClass(): string { return DebatAdminType::class; }
    protected function routePrefix(): string { return 'admin_debat_'; }
    protected function title(): string { return 'Débats'; }
    protected function orderBy(): array { return ['createdAt' => 'DESC']; }
    protected function columns(): array
    {
        return ['Titre' => 'title', 'Thème' => 'theme', 'Statut' => 'statusLabel', 'Créé le' => 'createdAt'];
    }
}
