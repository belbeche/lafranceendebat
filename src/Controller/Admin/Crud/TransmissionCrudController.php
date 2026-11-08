<?php

namespace App\Controller\Admin\Crud;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Transmission;
use App\Form\Admin\TransmissionAdminType;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP « TransmissionCrudController ».
 *
 * Routes :
 *  - GET /admin/transmissions (admin_transmission_)
 * Accès : ROLE_ADMIN.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/transmissions', name: 'admin_transmission_')]
class TransmissionCrudController extends AbstractCrudController
{
    protected function entityClass(): string { return Transmission::class; }
    protected function formClass(): string { return TransmissionAdminType::class; }
    protected function routePrefix(): string { return 'admin_transmission_'; }
    protected function title(): string { return 'Transmissions aux institutions'; }
    protected function orderBy(): array { return ['updatedAt' => 'DESC']; }
    protected function columns(): array
    {
        return ['Objet' => 'subjectTitle', 'Destinataire' => 'recipient', 'Voie' => 'routeLabel', 'Statut' => 'statusLabel', 'Envoyée le' => 'sentAt'];
    }
}
