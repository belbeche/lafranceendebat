<?php

namespace App\Controller\Admin\Crud;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Event;
use App\Form\Admin\EventAdminType;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP "EventCrudController".
 *
 * Routes :
 *  - GET /admin/agenda (admin_event_)
 * Accès : ROLE_ADMIN.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/agenda', name: 'admin_event_')]
class EventCrudController extends AbstractCrudController
{
    protected function entityClass(): string { return Event::class; }
    protected function formClass(): string { return EventAdminType::class; }
    protected function routePrefix(): string { return 'admin_event_'; }
    protected function title(): string { return 'Agenda des rencontres'; }
    protected function orderBy(): array { return ['date' => 'DESC']; }
    protected function columns(): array
    {
        return ['Nom' => 'name', 'Type' => 'kindLabel', 'Date' => 'date', 'Lieu' => 'location', 'Statut' => 'status'];
    }
}
