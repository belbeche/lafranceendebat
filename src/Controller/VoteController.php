<?php

namespace App\Controller;

use App\Controller\Concern\SafeRedirectTrait;
use App\Entity\Proposition;
use App\Entity\User;
use App\Service\PropositionSupportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Soutien consultatif. Ce n'est pas un vote electoral : voir la page "Consultations et votes".
 */
#[IsGranted('ROLE_USER')]
class VoteController extends AbstractController
{
    use SafeRedirectTrait;

    #[Route('/proposition/{id}/soutenir', name: 'app_proposition_support', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function support(Proposition $proposition, Request $request, PropositionSupportService $service): Response
    {
        return $this->handle($proposition, $request, 'support', fn (User $u) => $service->support($proposition, $u));
    }

    #[Route('/proposition/{id}/retirer-soutien', name: 'app_proposition_unsupport', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function withdraw(Proposition $proposition, Request $request, PropositionSupportService $service): Response
    {
        return $this->handle($proposition, $request, 'support', fn (User $u) => $service->withdraw($proposition, $u));
    }

    private function handle(Proposition $proposition, Request $request, string $tokenPrefix, callable $action): Response
    {
        $wantsJson = str_contains((string) $request->headers->get('Accept', ''), 'application/json');

        if (!$this->isCsrfTokenValid($tokenPrefix . $proposition->getId(), (string) $request->request->get('_token'))) {
            return $wantsJson
                ? new JsonResponse(['ok' => false, 'message' => 'Session expirée, rechargez la page.'], 419)
                : $this->failBack($request, $proposition, 'Session expirée. Réessayez.');
        }

        /** @var User $user */
        $user = $this->getUser();
        $result = $action($user);

        $messages = [
            PropositionSupportService::RESULT_ADDED => ['success', 'Votre soutien est enregistré. Il est consultatif et ne remplace pas un vote officiel.'],
            PropositionSupportService::RESULT_ALREADY => ['info', 'Vous soutenez déjà cette proposition.'],
            PropositionSupportService::RESULT_REMOVED => ['info', 'Votre soutien a été retiré.'],
            PropositionSupportService::RESULT_NOT_FOUND => ['info', 'Vous ne souteniez pas cette proposition.'],
            PropositionSupportService::RESULT_UNAVAILABLE => ['warning', 'Cette proposition n\'est pas ouverte au soutien.'],
        ];
        [$type, $text] = $messages[$result];

        if ($wantsJson) {
            return new JsonResponse([
                'ok' => in_array($result, [PropositionSupportService::RESULT_ADDED, PropositionSupportService::RESULT_REMOVED], true),
                'result' => $result,
                'supportCount' => $proposition->getSupportCount(),
                'message' => $text,
            ]);
        }

        $this->addFlash($type, $text);

        return $this->redirect($this->safeBackUrl($request, $this->generateUrl('app_proposition_show', ['id' => $proposition->getId()])));
    }

    private function failBack(Request $request, Proposition $proposition, string $message): Response
    {
        $this->addFlash('danger', $message);

        return $this->redirect($this->safeBackUrl($request, $this->generateUrl('app_proposition_show', ['id' => $proposition->getId()])));
    }
}
