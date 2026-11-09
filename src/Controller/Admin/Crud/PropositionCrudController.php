<?php

namespace App\Controller\Admin\Crud;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Proposition;
use App\Form\Admin\PropositionAdminType;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP "PropositionCrudController".
 *
 * Routes :
 *  - GET /admin/propositions (admin_proposition_)
 * Accès : ROLE_ADMIN.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/propositions', name: 'admin_proposition_')]
class PropositionCrudController extends AbstractCrudController
{
    protected function entityClass(): string { return Proposition::class; }
    protected function formClass(): string { return PropositionAdminType::class; }
    protected function routePrefix(): string { return 'admin_proposition_'; }
    protected function title(): string { return 'Propositions'; }
    protected function orderBy(): array { return ['createdAt' => 'DESC']; }
    protected function columns(): array
    {
        return ['Titre' => 'title', 'Thème' => 'theme', 'Statut' => 'status', 'Soutiens' => 'supportCount', 'Déposée le' => 'createdAt'];
    }
}
