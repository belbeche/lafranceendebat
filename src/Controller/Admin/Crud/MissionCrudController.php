<?php

namespace App\Controller\Admin\Crud;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Mission;
use App\Form\Admin\MissionAdminType;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP « MissionCrudController ».
 *
 * Routes :
 *  - GET /admin/piliers (admin_mission_)
 * Accès : ROLE_ADMIN.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/piliers', name: 'admin_mission_')]
class MissionCrudController extends AbstractCrudController
{
    protected function entityClass(): string { return Mission::class; }
    protected function formClass(): string { return MissionAdminType::class; }
    protected function routePrefix(): string { return 'admin_mission_'; }
    protected function title(): string { return 'Piliers et actions pour la paix'; }
    protected function orderBy(): array { return ['ordreAffichage' => 'ASC']; }
    protected function columns(): array
    {
        return ['Titre' => 'titre', 'Type' => 'type', 'Ordre' => 'ordreAffichage', 'Actif' => 'isActive'];
    }
}
