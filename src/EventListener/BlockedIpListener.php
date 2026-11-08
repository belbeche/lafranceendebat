<?php
// src/EventListener/BlockedIpListener.php

namespace App\EventListener;

use App\Repository\BlockedIpRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;

/**
 * Écouteur d'événement Symfony « BlockedIpListener ».
 */
class BlockedIpListener
{
    private BlockedIpRepository $blockedIpRepository;
    private LoggerInterface $logger;
    private array $whitelist;

    public function __construct(
        BlockedIpRepository $blockedIpRepository, 
        LoggerInterface $logger,
        array $whitelist = ['127.0.0.1', '::1'] // IP locales toujours autorisées
    ) {
        $this->blockedIpRepository = $blockedIpRepository;
        $this->logger = $logger;
        $this->whitelist = $whitelist;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $clientIp = $request->getClientIp();

        // Ignorer les IP de la whitelist
        if (in_array($clientIp, $this->whitelist)) {
            return;
        }

        // Ignorer les routes admin pour les admins connectés
        if (str_starts_with($request->getPathInfo(), '/admin') && $this->isAdminLoggedIn()) {
            return;
        }

        // Vérifier si l'IP est bloquée
        if ($this->blockedIpRepository->isBlocked($clientIp)) {
            $this->logger->warning('Tentative d\'accès depuis IP bloquée', [
                'ip' => $clientIp,
                'uri' => $request->getUri()
            ]);

            // Soit on lance une exception
            // throw new AccessDeniedHttpException('Accès refusé');

            // Soit on retourne une page personnalisée
            $response = new Response(
                $this->renderBlockedPage(),
                Response::HTTP_FORBIDDEN
            );
            $event->setResponse($response);
        }
    }

    private function isAdminLoggedIn(): bool
    {
        // À implémenter selon votre système d'auth
        // Par exemple, via le token de session ou le firewall
        return false;
    }

    private function renderBlockedPage(): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <title>Accès refusé</title>
            <style>
                body { 
                    font-family: Arial, sans-serif;
                    background: linear-gradient(135deg, #0a1a2d 0%, #1a365d 100%);
                    color: white;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                }
                .blocked-container {
                    text-align: center;
                    padding: 40px;
                    background: rgba(0,0,0,0.3);
                    border-radius: 20px;
                    border: 1px solid #ffd700;
                }
                h1 { color: #ffd700; font-size: 48px; }
                p { font-size: 18px; opacity: 0.8; }
            </style>
        </head>
        <body>
            <div class="blocked-container">
                <h1>⛔ ACCÈS REFUSÉ</h1>
                <p>Votre adresse IP a été bloquée pour des raisons de sécurité.</p>
                <p>Si vous pensez qu'il s'agit d'une erreur, contactez l'administrateur.</p>
            </div>
        </body>
        </html>
        HTML;
    }
}