<?php

namespace App\Controller;

use App\Entity\Survey;
use App\Entity\SurveyResponse;
use App\Entity\User;
use App\Service\ContentModerator;
use App\Service\Survey\SurveyResults;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Enquetes publiques : repondre sans compte (une fois par personne), consulter les resultats une fois cinq reponses recueillies. */
class SurveyController extends AbstractController
{
    #[Route('/enquetes', name: 'app_surveys', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $open = array_values(array_filter($em->getRepository(Survey::class)->findBy(['status' => Survey::OPEN], ['openedAt' => 'DESC']), static fn (Survey $s) => $s->isOpen()));
        $closed = $em->getRepository(Survey::class)->findBy(['status' => Survey::CLOSED, 'resultsPublic' => true], ['openedAt' => 'DESC'], 20);

        return $this->render('survey/index.html.twig', ['open' => $open, 'closed' => $closed]);
    }

    #[Route('/enquetes/{id<\d+>}', name: 'app_survey_show', methods: ['GET', 'POST'])]
    public function show(int $id, Request $request, EntityManagerInterface $em, ContentModerator $moderator, CacheItemPoolInterface $cache, SurveyResults $results): Response
    {
        $survey = $em->getRepository(Survey::class)->find($id);
        if (!$survey || $survey->isDraft()) {
            throw $this->createNotFoundException();
        }
        /** @var User|null $user */
        $user = $this->getUser();
        $token = (string) $request->cookies->get('lfed_sv', '');
        $newToken = null;
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            $token = $newToken = bin2hex(random_bytes(16));
        }
        $key = $user ? 'u' . $user->getId() : 'a' . hash('sha256', $token);
        $done = (bool) $em->getRepository(SurveyResponse::class)->findOneBy(['survey' => $survey, 'respondentKey' => $key]);
        $errors = [];
        $values = [];
        if ($request->isMethod('POST') && $survey->isOpen() && !$done) {
            $values = (array) $request->request->all('q');
            if ('' !== trim((string) $request->request->get('website', ''))) {
                return $this->redirectToRoute('app_surveys'); // robot
            }
            if (!$this->isCsrfTokenValid('survey' . $survey->getId(), (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée. Réessayez.';
            }
            $hit = $cache->getItem('sv_rate_' . md5((string) $request->getClientIp()));
            $n = $hit->isHit() ? (int) $hit->get() : 0;
            if ($n >= 30) {
                $errors[] = 'Trop de réponses depuis votre connexion. Réessayez plus tard.';
            }
            $answers = [];
            foreach ($survey->getQuestions() as $i => $q) {
                $v = $values[$i] ?? null;
                $label = 'Question ' . ($i + 1);
                if ('multiple' === $q['type']) {
                    $v = array_values(array_unique(array_map('strval', (array) $v)));
                    $v = array_values(array_filter($v, static fn ($x) => in_array($x, $q['options'], true)));
                } elseif ('texte' === $q['type']) {
                    $v = mb_substr(trim((string) $v), 0, 1000);
                    if ('' !== $v && $moderator->analyze($v) !== []) {
                        $errors[] = $label . ' : le texte contient un terme non autorisé. Reformulez calmement.';
                        $v = '';
                    }
                } else {
                    $v = is_scalar($v) ? (string) $v : '';
                    $valid = 'echelle' === $q['type'] ? ['1', '2', '3', '4', '5'] : $q['options'];
                    if ('' !== $v && !in_array($v, $valid, true)) {
                        $v = '';
                    }
                }
                if ($q['required'] && ('' === $v || [] === $v)) {
                    $errors[] = $label . ' : une réponse est obligatoire.';
                }
                $answers[$i] = $v;
            }
            if (!$errors) {
                $em->persist(new SurveyResponse($survey, $key, $user ? 'membre' : null, $answers));
                try {
                    $em->flush();
                } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
                    // double envoi : la premiere reponse est deja enregistree
                }
                $cache->save($hit->set($n + 1)->expiresAfter(3600));
                $resp = $this->redirectToRoute('app_survey_thanks', ['id' => $survey->getId()]);
                if ($newToken) {
                    $resp->headers->setCookie(Cookie::create('lfed_sv', $newToken, strtotime('+1 year'), '/', null, $request->isSecure(), true, false, 'lax'));
                }

                return $resp;
            }
        }
        $resp = $this->render('survey/form.html.twig', ['s' => $survey, 'done' => $done, 'errors' => $errors, 'values' => $values, 'preview' => false, 'canSeeResults' => $survey->isResultsPublic() && $results->count($survey) >= Survey::MIN_PUBLIC_RESPONSES], new Response(status: $errors ? 422 : 200));
        if ($newToken) {
            $resp->headers->setCookie(Cookie::create('lfed_sv', $newToken, strtotime('+1 year'), '/', null, $request->isSecure(), true, false, 'lax'));
        }

        return $resp;
    }

    #[Route('/enquetes/{id<\d+>}/merci', name: 'app_survey_thanks', methods: ['GET'])]
    public function thanks(Survey $survey, SurveyResults $results): Response
    {
        if ($survey->isDraft()) {
            throw $this->createNotFoundException();
        }

        return $this->render('survey/thanks.html.twig', ['s' => $survey, 'canSeeResults' => $survey->isResultsPublic() && $results->count($survey) >= Survey::MIN_PUBLIC_RESPONSES]);
    }

    #[Route('/enquetes/{id<\d+>}/resultats', name: 'app_survey_results', methods: ['GET'])]
    public function results(Survey $survey, SurveyResults $results): Response
    {
        if ($survey->isDraft() || !$survey->isResultsPublic()) {
            throw $this->createNotFoundException();
        }
        $n = $results->count($survey);
        if ($n < Survey::MIN_PUBLIC_RESPONSES) {
            return $this->render('survey/results.html.twig', ['s' => $survey, 'n' => $n, 'stats' => null]);
        }

        return $this->render('survey/results.html.twig', ['s' => $survey, 'n' => $n, 'stats' => $results->compute($survey)]);
    }
}
