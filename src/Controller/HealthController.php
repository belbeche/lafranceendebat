<?php

namespace App\Controller;

use App\Service\MaintenanceMode;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/** État du site pour une sonde de surveillance (UptimeRobot, etc.). Aucune donnée sensible. */
class HealthController extends AbstractController
{
    #[Route('/sante', name: 'app_health', methods: ['GET', 'HEAD'])]
    public function __invoke(Connection $db, MaintenanceMode $maintenance): JsonResponse
    {
        $dbOk = true;
        try {
            $db->executeQuery('SELECT 1')->fetchOne();
        } catch (\Throwable) {
            $dbOk = false;
        }
        $on = $maintenance->isOn();
        $ok = $dbOk && !$on;

        return new JsonResponse(['status' => $ok ? 'ok' : ($on ? 'maintenance' : 'panne'), 'base' => $dbOk ? 'ok' : 'injoignable', 'maintenance' => $on, 'heure' => date('c')], $ok ? 200 : 503, ['Cache-Control' => 'no-store']);
    }
}
