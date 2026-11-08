<?php
// src/EventSubscriber/VisitorTrackingSubscriber.php

namespace App\EventSubscriber;

use App\Service\VisitorTrackerService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Abonné aux événements Symfony « VisitorTrackingSubscriber ».
 */
class VisitorTrackingSubscriber implements EventSubscriberInterface
{
    private VisitorTrackerService $visitorTracker;

    public function __construct(VisitorTrackerService $visitorTracker)
    {
        $this->visitorTracker = $visitorTracker;
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        
        // Ne tracker que les requêtes GET principales
        if (!$request->isMethod('GET') || $request->isXmlHttpRequest()) {
            return;
        }

        // Exclure certaines routes
        $currentRoute = $request->attributes->get('_route', '');
        
        $excludedRoutes = [
            'admin_', '_wdt', '_profiler', 'app_login', 
            'api_', '_configurator', '/_error'
        ];

        foreach ($excludedRoutes as $excluded) {
            if (strpos($currentRoute, $excluded) === 0) {
                return;
            }
        }

        // Exclure les assets et fichiers médias
        $path = $request->getPathInfo();
        if (preg_match('#\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$#', $path)) {
            return;
        }

        // Track la visite
        $this->visitorTracker->trackVisit($currentRoute);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}