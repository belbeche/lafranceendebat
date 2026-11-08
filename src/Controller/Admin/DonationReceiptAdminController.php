<?php

namespace App\Controller\Admin;

use App\Entity\DonationReceipt;
use App\Service\DonationReceiptService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur HTTP « DonationReceiptAdminController ».
 *
 * Routes :
 *  - GET,POST /admin/recus-fiscaux (admin_receipts)
 *  - GET /admin/recus-fiscaux/{id<\d+>}.pdf (admin_receipt_pdf)
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
#[Route('/admin/recus-fiscaux')]
class DonationReceiptAdminController extends AbstractController
{
    #[Route('', name: 'admin_receipts', methods: ['GET', 'POST'])]
    public function index(Request $request, DonationReceiptService $service, EntityManagerInterface $em): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('receipt_new', (string) $request->request->get('_token'))) {
                $error = 'Session expirée.';
            } else {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->request->get('date'));
                $cents = (int) round(((float) str_replace(',', '.', (string) $request->request->get('amount'))) * 100);
                try {
                    if (!$date) {
                        throw new \DomainException('Date invalide.');
                    }
                    $r = $service->issue((string) $request->request->get('donor'), (string) $request->request->get('address'), $cents, $date, (string) $request->request->get('mode'), $this->getUser());
                    $this->addFlash('success', 'Reçu ' . $r->getNumber() . ' créé.');

                    return $this->redirectToRoute('admin_receipts');
                } catch (\DomainException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('admin/receipts/index.html.twig', [
            'enabled' => $service->isEnabled(), 'missing' => $service->missing(), 'error' => $error,
            'receipts' => $em->getRepository(DonationReceipt::class)->findBy([], ['id' => 'DESC'], 200), 'modes' => DonationReceipt::MODES,
        ]);
    }

    #[Route('/{id<\d+>}.pdf', name: 'admin_receipt_pdf', methods: ['GET'])]
    public function pdf(DonationReceipt $r, DonationReceiptService $service): Response
    {
        if (!$service->isEnabled()) {
            throw $this->createNotFoundException();
        }

        return new Response($service->pdf($r), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="recu-' . $r->getNumber() . '.pdf"']);
    }
}
