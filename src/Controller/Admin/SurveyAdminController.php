<?php

namespace App\Controller\Admin;

use App\Entity\Survey;
use App\Service\ContentModerator;
use App\Service\Survey\SurveyResults;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Enquetes : brouillon, apercu OBLIGATOIRE de la version exacte, publication, envoi aux abonnes confirmes, cloture, resultats. */
#[Route('/admin/enquetes')]
#[IsGranted('ROLE_MODERATOR')]
class SurveyAdminController extends AbstractController
{
    #[Route('', name: 'admin_surveys', methods: ['GET'])]
    public function index(EntityManagerInterface $em, SurveyResults $results): Response
    {
        $list = $em->getRepository(Survey::class)->findBy([], ['id' => 'DESC']);
        $counts = [];
        foreach ($list as $s) {
            $counts[$s->getId()] = $results->count($s);
        }

        return $this->render('admin/enquetes/index.html.twig', ['list' => $list, 'counts' => $counts]);
    }

    #[Route('/nouvelle', name: 'admin_survey_new', methods: ['GET', 'POST'])]
    #[Route('/{id<\d+>}/modifier', name: 'admin_survey_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $em, ContentModerator $moderator, ?Survey $survey = null): Response
    {
        $survey ??= (new Survey())->setCreatedBy($this->getUser());
        if ($survey->getId() && !$survey->isDraft()) {
            $this->addFlash('danger', 'Une enquête publiée ne se modifie plus (les réponses seraient faussées). Clôturez-la et créez-en une nouvelle.');

            return $this->redirectToRoute('admin_surveys');
        }
        $errors = [];
        $d = ['title' => $survey->getTitle(), 'intro' => $survey->getIntro(), 'questions' => $survey->questionsAsText(), 'public' => $survey->isResultsPublic(), 'closes' => $survey->getClosesAt()?->format('Y-m-d') ?? ''];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('survey_edit', (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée.';
            }
            $d = ['title' => trim((string) $request->request->get('title')), 'intro' => trim((string) $request->request->get('intro')), 'questions' => (string) $request->request->get('questions'), 'public' => $request->request->getBoolean('public'), 'closes' => trim((string) $request->request->get('closes'))];
            if (mb_strlen($d['title']) < 5 || mb_strlen($d['title']) > 160) {
                $errors[] = 'Titre : 5 à 160 caractères.';
            }
            if (mb_strlen($d['intro']) < 20 || mb_strlen($d['intro']) > 1500) {
                $errors[] = 'Présentation : 20 à 1500 caractères (elle sert aussi de message d\'invitation).';
            }
            [$questions, $qerr] = Survey::parse($d['questions']);
            $errors = array_merge($errors, $qerr);
            $closes = null;
            if ('' !== $d['closes']) {
                $closes = \DateTimeImmutable::createFromFormat('!Y-m-d', $d['closes']) ?: null;
                if (!$closes || $closes < new \DateTimeImmutable('today')) {
                    $errors[] = 'Date de clôture invalide ou passée.';
                    $closes = null;
                }
            }
            if ($moderator->analyze($d['title'] . ' ' . $d['intro'] . ' ' . json_encode($questions, JSON_UNESCAPED_UNICODE)) !== []) {
                $errors[] = 'Le texte contient un terme non autorisé par la charte.';
            }
            if (!$errors) {
                $survey->setTitle($d['title'])->setIntro($d['intro'])->setQuestions($questions)->setResultsPublic($d['public'])->setClosesAt($closes ? $closes->setTime(23, 59) : null);
                $em->persist($survey);
                $em->flush();
                $this->addFlash('success', 'Brouillon enregistré. Relisez l\'aperçu avant de publier.');

                return $this->redirectToRoute('admin_survey_preview', ['id' => $survey->getId()]);
            }
        }

        return $this->render('admin/enquetes/form.html.twig', ['s' => $survey, 'd' => $d, 'errors' => $errors, 'types' => Survey::TYPES]);
    }

    #[Route('/{id<\d+>}/apercu', name: 'admin_survey_preview', methods: ['GET'])]
    public function preview(Survey $survey, EntityManagerInterface $em, SurveyResults $results): Response
    {
        $survey->markPreviewed();
        $em->flush();

        return $this->render('survey/form.html.twig', ['s' => $survey, 'done' => false, 'errors' => [], 'values' => [], 'preview' => true, 'canSeeResults' => false]);
    }

    #[Route('/{id<\d+>}/{action}', name: 'admin_survey_act', requirements: ['action' => 'publier|envoyer|cloturer|supprimer'], methods: ['POST'])]
    public function act(Survey $survey, string $action, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('survey' . $survey->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée.');

            return $this->redirectToRoute('admin_surveys');
        }
        switch ($action) {
            case 'publier':
                if (!$survey->isDraft()) {
                    break;
                }
                if (!$survey->isPreviewed()) {
                    $this->addFlash('danger', 'Ouvrez l\'aperçu de la version actuelle avant de publier.');

                    return $this->redirectToRoute('admin_survey_preview', ['id' => $survey->getId()]);
                }
                $survey->publish();
                if ($request->request->getBoolean('envoyer')) {
                    $survey->queueSend();
                }
                $em->flush();
                $this->addFlash('success', 'Enquête publiée' . ('sending' === $survey->getSendStatus() ? ' : l\'invitation part par lots aux abonnés confirmés.' : '.'));
                break;
            case 'envoyer':
                if ($survey->getStatus() === Survey::OPEN && 'none' === $survey->getSendStatus()) {
                    $survey->queueSend();
                    $em->flush();
                    $this->addFlash('success', 'Invitation mise en file : elle part par lots aux abonnés confirmés (une seule fois par personne).');
                }
                break;
            case 'cloturer':
                $survey->close();
                $em->flush();
                $this->addFlash('success', 'Enquête clôturée.');
                break;
            case 'supprimer':
                if (!$this->isGranted('ROLE_ADMIN')) {
                    throw $this->createAccessDeniedException();
                }
                $em->remove($survey);
                $em->flush();
                $this->addFlash('success', 'Enquête et réponses supprimées.');
                break;
        }

        return $this->redirectToRoute('admin_surveys');
    }

    #[Route('/{id<\d+>}/resultats', name: 'admin_survey_results', methods: ['GET'])]
    public function results(Survey $survey, SurveyResults $results): Response
    {
        return $this->render('admin/enquetes/results.html.twig', ['s' => $survey, 'n' => $results->count($survey), 'stats' => $results->compute($survey, true)]);
    }

    #[Route('/{id<\d+>}/export.csv', name: 'admin_survey_export', methods: ['GET'])]
    public function export(Survey $survey, SurveyResults $results): Response
    {
        $rows = $results->rows($survey);

        return new StreamedResponse(function () use ($rows) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            if ($rows) {
                fputcsv($h, array_keys($rows[0]), ';');
            }
            foreach ($rows as $r) {
                fputcsv($h, array_map(static fn ($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : $v, $r), ';');
            }
            fclose($h);
        }, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="enquete-' . $survey->getId() . '.csv"']);
    }
}
