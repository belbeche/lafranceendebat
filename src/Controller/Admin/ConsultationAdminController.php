<?php

namespace App\Controller\Admin;

use App\Entity\Consultation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur HTTP « ConsultationAdminController ».
 *
 * Routes :
 *  - GET,POST /admin/scrutins/nouveau (admin_consultation_new)
 * Accès : ROLE_ADMIN.
 * Les formulaires qui modifient des données sont protégés par un jeton CSRF.
 */
#[Route('/admin/scrutins')]
#[IsGranted('ROLE_ADMIN')]
class ConsultationAdminController extends AbstractController
{
    #[Route('/nouveau', name: 'admin_consultation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $errors = [];
        $d = ['question' => '', 'context' => '', 'options' => '', 'closes' => (new \DateTimeImmutable('+14 days'))->format('Y-m-d')];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('consultation_new', (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée.';
            } else {
                $d = array_merge($d, array_map('strval', array_intersect_key($request->request->all(), $d)));
                $options = array_values(array_unique(array_filter(array_map(static fn ($l) => mb_substr(trim($l), 0, 80), preg_split('/\R/', $d['options']) ?: []))));
                try {
                    $closes = new \DateTimeImmutable($d['closes'] . ' 23:59:59');
                } catch (\Exception) {
                    $closes = null;
                    $errors[] = 'Date de clôture invalide.';
                }
                if (mb_strlen(trim($d['question'])) < 8 || mb_strlen($d['question']) > 160) {
                    $errors[] = 'La question doit faire entre 8 et 160 caractères.';
                }
                if (count($options) < 2 || count($options) > 6) {
                    $errors[] = 'Indiquez de 2 à 6 réponses, une par ligne.';
                }
                if ($closes && $closes <= new \DateTimeImmutable()) {
                    $errors[] = 'La clôture doit être dans le futur.';
                }
                if (!$errors) {
                    $c = (new Consultation())->setQuestion($d['question'])->setContext($d['context'])->setOptions($options)->setClosesAt($closes);
                    $em->persist($c);
                    $em->flush();
                    $this->addFlash('success', 'Scrutin publié.');

                    return $this->redirectToRoute('app_consultation_show', ['id' => $c->getId()]);
                }
            }
        }

        return $this->render('admin/consultation/new.html.twig', ['d' => $d, 'errors' => $errors]);
    }
}
