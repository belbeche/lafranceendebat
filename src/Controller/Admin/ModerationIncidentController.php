<?php

namespace App\Controller\Admin;

use App\Entity\ModerationIncident;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur HTTP "ModerationIncidentController".
 *
 * Routes :
 *  - GET /admin/signalements-filtre (admin_moderation_incidents)
 *  - POST /admin/signalements-filtre/{id}/traite (admin_moderation_incident_done)
 * Accès : ROLE_MODERATOR.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
#[Route('/admin/signalements-filtre')]
#[IsGranted('ROLE_MODERATOR')]
class ModerationIncidentController extends AbstractController
{
    #[Route('', name: 'admin_moderation_incidents', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $incidents = $em->getRepository(ModerationIncident::class)->findBy([], ['createdAt' => 'DESC'], 200);
        $repeat = $em->createQuery('SELECT IDENTITY(i.user) AS uid, COUNT(i.id) AS n FROM App\Entity\ModerationIncident i WHERE i.user IS NOT NULL AND i.createdAt > :d GROUP BY i.user')
            ->setParameter('d', new \DateTimeImmutable('-30 days'))->getArrayResult();
        $counts = [];
        foreach ($repeat as $r) {
            $counts[(int) $r['uid']] = (int) $r['n'];
        }

        return $this->render('admin/moderation/incidents.html.twig', ['incidents' => $incidents, 'counts' => $counts]);
    }

    #[Route('/{id}/traite', name: 'admin_moderation_incident_done', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function done(ModerationIncident $incident, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('incident' . $incident->getId(), (string) $request->request->get('_token'))) {
            $incident->markHandled();
            $em->flush();
        }

        return $this->redirectToRoute('admin_moderation_incidents');
    }
}
