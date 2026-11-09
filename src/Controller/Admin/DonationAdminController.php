<?php

namespace App\Controller\Admin;

use App\Entity\Donation;
use App\Service\DonationService;
use App\Service\TransactionalMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des contributions de soutien (table Donation).
 *
 *  - GET  /admin/contributions                 tableau de bord : effectifs et montants PAR STATUT, liste filtrable
 *  - GET  /admin/contributions/{id}            détail
 *  - POST /admin/contributions/{id}/annuler    passe une contribution "en attente" à "annulée" (jamais une payée)
 *  - POST /admin/contributions/{id}/supprimer  supprime une contribution non payée (en attente ou annulée)
 *  - GET  /admin/contributions/export.csv      export comptable (sans adresse postale)
 *
 * Règles : une contribution PAYÉE est une pièce comptable (conservation 10 ans) : elle ne peut ni être annulée ni
 * supprimée ici. Les montants "en attente" ne sont PAS du chiffre d'affaires : le visiteur a ouvert la page de
 * paiement mais n'a pas payé (ou le webhook Stripe n'est pas encore arrivé). Chaque action exige un jeton CSRF.
 */
#[Route('/admin/contributions')]
#[IsGranted('ROLE_ADMIN')]
class DonationAdminController extends AbstractController
{
    private const LABELS = [Donation::PENDING => 'En attente de paiement', Donation::PAID => 'Payées', Donation::CANCELLED => 'Annulées'];

    #[Route('', name: 'admin_donations', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $stats = [];
        foreach (array_keys(self::LABELS) as $s) {
            $stats[$s] = ['count' => 0, 'cents' => 0];
        }
        foreach ($em->createQuery('SELECT d.status AS s, COUNT(d.id) AS n, COALESCE(SUM(d.amountCents),0) AS c FROM App\Entity\Donation d GROUP BY d.status')->getArrayResult() as $r) {
            $stats[$r['s']] = ['count' => (int) $r['n'], 'cents' => (int) $r['c']];
        }
        $status = (string) $request->query->get('statut', '');
        $qb = $em->getRepository(Donation::class)->createQueryBuilder('d');
        if (isset(self::LABELS[$status])) {
            $qb->andWhere('d.status = :s')->setParameter('s', $status);
        }
        if ($q = trim((string) $request->query->get('q'))) {
            $qb->andWhere('d.donorName LIKE :q OR d.donorEmail LIKE :q')->setParameter('q', '%' . addcslashes(mb_substr($q, 0, 80), '%_') . '%');
        }

        return $this->render('admin/donations/index.html.twig', [
            'items' => $qb->orderBy('d.id', 'DESC')->setMaxResults(200)->getQuery()->getResult(), 'stats' => $stats, 'labels' => self::LABELS, 'f' => ['statut' => $status, 'q' => $q],
        ]);
    }

    #[Route('/export.csv', name: 'admin_donations_export', methods: ['GET'])]
    public function export(EntityManagerInterface $em): Response
    {
        $rows = $em->getRepository(Donation::class)->findBy([], ['id' => 'ASC']);

        return new StreamedResponse(static function () use ($rows) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, ['id', 'date', 'statut', 'montant_eur', 'nom', 'email'], ';');
            foreach ($rows as $d) {
                $safe = static fn (string $v): string => preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
                fputcsv($h, [$d->getId(), $d->getCreatedAt()->format('Y-m-d H:i'), $d->getStatus(), number_format($d->getAmountCents() / 100, 2, ',', ''), $safe($d->getDonorName()), $safe($d->getDonorEmail())], ';');
            }
            fclose($h);
        }, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="contributions.csv"']);
    }

    #[Route('/{id<\d+>}', name: 'admin_donation_show', methods: ['GET'])]
    public function show(Donation $d, DonationService $donations): Response
    {
        return $this->render('admin/donations/show.html.twig', ['d' => $d, 'labels' => self::LABELS, 'pageUrl' => $donations->pageUrl($d)]);
    }

    /** Renvoie par e-mail le lien personnel de la page de contribution, pour quelqu'un qui l'a perdue. */
    #[Route('/{id<\d+>}/lien', name: 'admin_donation_link', methods: ['POST'])]
    public function resendLink(Donation $d, Request $request, DonationService $donations, TransactionalMailer $mailer): Response
    {
        $this->csrf($request, 'donation' . $d->getId());
        if (Donation::CANCELLED === $d->getStatus()) {
            $this->addFlash('danger', 'Contribution annulée : il n\'y a plus de page à retrouver.');
        } else {
            $mailer->contributionLink($d->getDonorEmail(), $d->getDonorName(), $donations->pageUrl($d));
            $this->addFlash('success', 'Lien envoyé à ' . $d->getDonorEmail() . '.');
        }

        return $this->redirectToRoute('admin_donation_show', ['id' => $d->getId()]);
    }

    #[Route('/{id<\d+>}/annuler', name: 'admin_donation_cancel', methods: ['POST'])]
    public function cancel(Donation $d, Request $request, EntityManagerInterface $em): Response
    {
        $this->csrf($request, 'donation' . $d->getId());
        if (Donation::PENDING !== $d->getStatus()) {
            $this->addFlash('danger', 'Seule une contribution en attente peut être annulée. Une contribution payée est une pièce comptable.');
        } else {
            $d->setStatus(Donation::CANCELLED);
            $em->flush();
            $this->addFlash('success', 'Contribution annulée.');
        }

        return $this->redirectToRoute('admin_donations');
    }

    #[Route('/{id<\d+>}/supprimer', name: 'admin_donation_delete', methods: ['POST'])]
    public function delete(Donation $d, Request $request, EntityManagerInterface $em): Response
    {
        $this->csrf($request, 'donation' . $d->getId());
        if (Donation::PAID === $d->getStatus()) {
            $this->addFlash('danger', 'Une contribution payée ne se supprime pas : elle doit être conservée 10 ans pour la comptabilité.');
        } else {
            $em->remove($d);
            $em->flush();
            $this->addFlash('success', 'Contribution supprimée (non payée).');
        }

        return $this->redirectToRoute('admin_donations');
    }

    private function csrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
