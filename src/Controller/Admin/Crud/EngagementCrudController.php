<?php

namespace App\Controller\Admin\Crud;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Engagement;
use App\Form\Admin\EngagementAdminType;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP "EngagementCrudController".
 *
 * Routes :
 *  - GET /admin/engagements (admin_engagement_)
 * Accès : ROLE_ADMIN.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/engagements', name: 'admin_engagement_')]
class EngagementCrudController extends AbstractCrudController
{
    protected function entityClass(): string { return Engagement::class; }
    protected function formClass(): string { return EngagementAdminType::class; }
    protected function routePrefix(): string { return 'admin_engagement_'; }
    protected function title(): string { return 'Engagements des candidats'; }
    protected function orderBy(): array { return ['updatedAt' => 'DESC']; }
    protected function columns(): array
    {
        return ['Engagement' => 'title', 'Candidat' => 'candidat', 'Thème' => 'theme', 'Statut' => 'statusLabel', 'Fiche complète' => 'sheetCompleteness'];
    }
}
