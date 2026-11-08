<?php

namespace App\Controller;

use App\Service\Audience\ConsentManager;
use App\Service\Audience\ViewTracker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Consentement maison : choisir, modifier, retirer. Le retrait est aussi simple que l'acceptation (une seule action). */
class ConsentController extends AbstractController
{
    #[Route('/cookies/choix', name: 'app_consent_choice', methods: ['POST'])]
    public function choice(Request $request, ConsentManager $consent): Response
    {
        if (!$this->isCsrfTokenValid('consent', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée. Réessayez.');

            return $this->back($request);
        }
        $c = (string) $request->request->get('choice', '');
        $resp = $this->back($request);
        match ($c) {
            'accept' => $consent->record($request, $resp, true, 'accept_all'),
            'refuse' => $consent->record($request, $resp, false, 'refuse_all'),
            'withdraw' => $consent->record($request, $resp, false, 'withdraw'),
            default => $consent->record($request, $resp, $request->request->getBoolean('audience'), 'custom'),
        };
        $this->addFlash('success', 'withdraw' === $c ? 'Votre consentement est retiré. La mesure du temps de lecture est arrêtée pour vous.' : 'Votre choix est enregistré. Vous pouvez le modifier à tout moment depuis « Cookies » en bas de page.');

        return $resp;
    }

    /** Balise de mesure de lecture : n'enregistre rien sans consentement. */
    #[Route('/mesure/lecture', name: 'app_read_meter', methods: ['POST'])]
    public function meter(Request $request, ConsentManager $consent, ViewTracker $views): Response
    {
        $kind = (string) $request->request->get('kind', '');
        if (!$consent->hasAudience($request) || !in_array($kind, ['debat', 'proposition'], true)) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }
        $views->recordRead($kind, $request->request->getInt('id'), $request, $request->request->getInt('seconds'), $request->request->getInt('scroll'));

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function back(Request $request): RedirectResponse
    {
        $to = (string) $request->request->get('back', '/cookie-preferences');
        if (!str_starts_with($to, '/') || str_starts_with($to, '//') || str_contains($to, '\\')) {
            $to = '/cookie-preferences';
        }

        return new RedirectResponse($to);
    }
}
