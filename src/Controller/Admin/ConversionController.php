<?php

namespace App\Controller\Admin;

use App\Service\Audience\ConversionTracker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ConversionController extends AbstractController
{
    #[Route('/admin/conversions', name: 'admin_conversions', methods: ['GET'])]
    public function index(ConversionTracker $tracker): Response
    {
        $rows = $tracker->lastDays(30);
        $tot = ['visits' => 0] + array_fill_keys(array_keys(ConversionTracker::EVENTS), 0);
        foreach ($rows as $r) {
            $tot['visits'] += $r['visits'];
            foreach ($r['events'] as $k => $v) {
                $tot[$k] = ($tot[$k] ?? 0) + $v;
            }
        }

        return $this->render('admin/conversions.html.twig', ['rows' => $rows, 'total' => $tot, 'labels' => ConversionTracker::EVENTS]);
    }
}
