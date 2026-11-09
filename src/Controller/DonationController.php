<?php

namespace App\Controller;

use App\Service\DonationReceiptService;
use App\Service\DonationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** Contributions de soutien en ligne (micro-entreprise : aucun reçu fiscal). Fermée tant que DONATIONS_OPEN vaut 0. */
class DonationController extends AbstractController
{
    #[Route('/soutenir/contribution', name: 'app_donate', methods: ['GET', 'POST'])]
    public function donate(Request $request, DonationService $donations): Response
    {
        if (!$donations->isOpen()) {
            throw $this->createNotFoundException();
        }
        $error = null;
        $data = ['name' => '', 'address' => '', 'email' => '', 'amount' => ''];
        if ($request->isMethod('POST')) {
            foreach ($data as $k => $_) {
                $data[$k] = trim((string) $request->request->get($k, ''));
            }
            if (!$this->isCsrfTokenValid('donate', (string) $request->request->get('_token'))) {
                $error = 'Session expirée, réessayez.';
            } elseif (!$request->request->getBoolean('no_political')) {
                $error = 'Confirmez que votre contribution n\'est destiné ni à un parti, ni à un candidat, ni à une campagne.';
            } else {
                try {
                    $r = $donations->start($data['name'], $data['address'], $data['email'], (int) round(((float) str_replace(',', '.', $data['amount'])) * 100),
                        str_replace(str_repeat('f', 48), '{TOKEN}', $this->generateUrl('app_donate_thanks', ['token' => str_repeat('f', 48)], UrlGeneratorInterface::ABSOLUTE_URL)),
                        $this->generateUrl('app_donate', [], UrlGeneratorInterface::ABSOLUTE_URL));

                    return $this->redirect($r['url']);
                } catch (\DomainException $e) {
                    $error = $e->getMessage();
                } catch (\Throwable $e) {
                    error_log($e::class.': '.$e->getMessage());
                    $error = 'Le paiement est momentanément indisponible. Aucun montant n\'a été débité.';
                }
            }
        }

        return $this->render('donation/form.html.twig', ['data' => $data, 'error' => $error, 'min' => DonationService::MIN_CENTS / 100, 'needsAddress' => $donations->issuesReceipts()]);
    }

    #[Route('/soutenir/merci/{token}', name: 'app_donate_thanks', requirements: ['token' => '[a-f0-9]{48}'], methods: ['GET'])]
    public function thanks(string $token, DonationService $donations): Response
    {
        $d = $donations->find($token) ?? throw $this->createNotFoundException();
        $donations->sync($d);

        return $this->render('donation/thanks.html.twig', ['donation' => $d]);
    }

    #[Route('/soutenir/recu/{token}.pdf', name: 'app_donate_receipt', requirements: ['token' => '[a-f0-9]{48}'], methods: ['GET'])]
    public function receipt(string $token, DonationService $donations, DonationReceiptService $receipts): Response
    {
        $d = $donations->find($token);
        if (!$d || !$d->getReceipt() || !$receipts->isEnabled()) {
            throw $this->createNotFoundException();
        }

        return new Response($receipts->pdf($d->getReceipt()), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="recu-' . $d->getReceipt()->getNumber() . '.pdf"', 'Cache-Control' => 'no-store, private']);
    }
}
