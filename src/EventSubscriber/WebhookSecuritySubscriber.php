<?php
// src/EventSubscriber/WebhookSecuritySubscriber.php

namespace App\EventSubscriber;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Protège les adresses /webhook/ : seules les IP listées dans la configuration y accèdent.
 * Pour autoriser un nouveau fournisseur, ajouter son IP à la variable d'environnement correspondante, pas dans le code.
 * La vérification de signature propre à chaque fournisseur reste dans son contrôleur.
 */
class WebhookSecuritySubscriber implements EventSubscriberInterface
{
    private array $allowedIps;
    
    public function __construct(string $allowedIps)
    {
        $this->allowedIps = array_filter(explode(',', $allowedIps));
    }
    
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Vérifier si c'est une route de webhook
        if (strpos($request->getPathInfo(), '/webhook/') !== 0) {
            return;
        }
        
        // Skip pour l'endpoint de test
        if ($request->getPathInfo() === '/webhook/test') {
            return;
        }
        
        $clientIp = $request->getClientIp();
        
        // Vérifier si l'IP est autorisée
        if (!empty($this->allowedIps) && !$this->isIpAllowed($clientIp)) {
            throw new AccessDeniedHttpException('IP not allowed for webhooks');
        }
        
        // Limiter le taux de requêtes pour les webhooks
        $this->rateLimit($request);
    }
    
    private function isIpAllowed(string $ip): bool
    {
        foreach ($this->allowedIps as $allowedIp) {
            if (fnmatch($allowedIp, $ip)) {
                return true;
            }
        }
        return false;
    }
    
    private function rateLimit(Request $request): void
    {
        // Implémentez votre logique de rate limiting ici
        // Par exemple avec symfony/rate-limiter
    }
    
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }
}