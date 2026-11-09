<?php

namespace App\Controller;

use App\Entity\ContentTranslation;
use App\Entity\User;
use App\Repository\ContentTranslationRepository;
use App\Service\ContentModerator;
use App\Service\ContentTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Le monde peut traduire : tout compte connecté propose une traduction, relue avant publication. */
#[IsGranted('ROLE_USER')]
class TranslationContributionController extends AbstractController
{
    #[Route('/traduire/{type}/{id<\d+>}/{locale}', name: 'app_translate_contribute', requirements: ['type' => 'revendication|proposition|discussion|service_offer', 'locale' => '[a-z]{2}'], methods: ['GET', 'POST'])]
    public function contribute(string $type, int $id, string $locale, Request $request, EntityManagerInterface $em, ContentTranslationRepository $repo, ContentModerator $moderator, RateLimiterFactory $translationUserLimiter): Response
    {
        [$class, $fields] = ContentTranslator::TYPES[$type];
        $entity = $em->find($class, $id);
        if (!$entity || !in_array($locale, \App\Entity\Revendication::LOCALES, true)) {
            throw $this->createNotFoundException();
        }
        if ($repo->findOneBy(['type' => $type, 'refId' => $id, 'locale' => $locale])) {
            $this->addFlash('info', 'Cette langue a déjà une traduction ou une proposition en cours de relecture.');

            return $this->redirectToRoute('app_home');
        }
        $errors = [];
        $values = array_fill_keys($fields, '');
        if ($request->isMethod('POST')) {
            foreach ($fields as $f) {
                $values[$f] = trim((string) $request->request->get($f, ''));
            }
            if (!$this->isCsrfTokenValid('translate' . $type . $id, (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée. Réessayez.';
            }
            if ('' === $values[$fields[0]] || '' === $values[$fields[1]]) {
                $errors[] = 'Le titre et le texte sont obligatoires.';
            }
            if (array_intersect($moderator->analyze(implode("\n", $values)), [ContentModerator::REASON_INSULT, ContentModerator::REASON_THREAT, ContentModerator::REASON_PERSONAL_DATA])) {
                $errors[] = 'Le texte contient une insulte, une menace ou une donnée personnelle.';
            }
            /** @var User $u */
            $u = $this->getUser();
            if (!$errors && !$translationUserLimiter->create('u' . $u->getId())->consume()->isAccepted()) {
                $errors[] = 'Limite quotidienne atteinte. Revenez demain.';
            }
            if (!$errors) {
                $em->persist(new ContentTranslation($type, $id, $locale, $values, ContentTranslation::SRC_USER, ContentTranslation::PENDING, $u));
                $em->flush();
                $this->addFlash('success', 'Merci. Votre traduction sera publiée après relecture.');

                return $this->redirectToRoute('app_home');
            }
        }
        $original = [];
        foreach ($fields as $f) {
            $g = 'get' . ucfirst($f);
            $original[$f] = (string) $entity->$g();
        }

        return $this->render('translation/contribute.html.twig', ['type' => $type, 'id' => $id, 'locale' => $locale, 'fields' => $fields, 'original' => $original, 'values' => $values, 'errors' => $errors]);
    }
}
