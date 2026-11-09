<?php

namespace App\Controller\Admin;

use App\Entity\Lettre;
use App\Entity\NewsletterSubscriber;
use App\Entity\User;
use App\Repository\LettreRepository;
use App\Service\Lettre\LettreMailer;
use App\Service\Lettre\LettreRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Envoi en masse securise : administrateur reconnecte, jeton CSRF sur chaque action, confirmation explicite avant envoi. */
#[Route('/admin/lettres')]
#[IsGranted('ROLE_ADMIN')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class LettreAdminController extends AbstractController
{
    #[Route('', name: 'admin_lettre_index', methods: ['GET'])]
    public function index(LettreRepository $repo, LettreMailer $mailer): Response
    {
        return $this->render('admin/lettre/index.html.twig', [
            'lettres' => $repo->findBy([], ['id' => 'DESC'], 50),
            'eligible' => $repo->countEligible(),
            'eligibleActors' => $repo->countFor((new Lettre())->setAudience(Lettre::AUD_INVITATION)),
            'consentedActors' => $repo->countFor((new Lettre())->setAudience(Lettre::AUD_ACTORS)),
            'problem' => $mailer->readinessProblem(),
        ]);
    }

    #[Route('/nouvelle', name: 'admin_lettre_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        $lettre = new Lettre();
        $lettre->setCreatedBy($this->getUser() instanceof User ? $this->getUser() : null);
        // ?modele=commune : pré-remplit objet, contenu et ciblage avec le message adapté à la catégorie.
        $modele = (string) $request->query->get('modele', '');
        if ($request->isMethod('GET') && isset(\App\Service\Lettre\ActorMessageTemplates::ACCROCHES[$modele])) {
            $m = \App\Service\Lettre\ActorMessageTemplates::modele($modele);
            $lettre->setSubject($m['subject'])->setBody($m['body'])->setAudience(Lettre::AUD_INVITATION)->setTarget($modele, null);
        }

        return $this->edit($request, $lettre, $em, $validator, true);
    }

    #[Route('/{id<\d+>}/modifier', name: 'admin_lettre_edit', methods: ['GET', 'POST'])]
    public function editAction(Request $request, Lettre $lettre, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        if (!$lettre->isEditable()) {
            $this->addFlash('error', 'Une lettre déjà mise en file ou envoyée ne peut plus être modifiée.');

            return $this->redirectToRoute('admin_lettre_index');
        }

        return $this->edit($request, $lettre, $em, $validator, false);
    }

    #[Route('/{id<\d+>}', name: 'admin_lettre_show', methods: ['GET'])]
    public function show(Lettre $lettre, LettreRenderer $renderer, LettreRepository $repo, LettreMailer $mailer): Response
    {
        return $this->render('admin/lettre/show.html.twig', [
            'lettre' => $lettre,
            'html' => $renderer->toHtml((string) $lettre->getBody()),
            'eligible' => $eligible = $repo->countFor($lettre),
            'diagnostic' => 0 === $eligible ? $repo->diagnose($lettre) : [],
            'problem' => $mailer->readinessProblem(),
        ]);
    }

    #[Route('/{id<\d+>}/test', name: 'admin_lettre_test', methods: ['POST'])]
    public function test(Request $request, Lettre $lettre, LettreMailer $mailer, EntityManagerInterface $em): Response
    {
        $this->checkCsrf($request, 'lettre_test' . $lettre->getId());
        if ($problem = $mailer->readinessProblem()) {
            $this->addFlash('error', $problem);

            return $this->redirectToRoute('admin_lettre_show', ['id' => $lettre->getId()]);
        }
        $me = $this->getUser();
        if ($lettre->isForActors()) {
            // Test demarchage : un contact factice (non enregistre), envoye uniquement a l'administrateur connecte.
            $fake = new \App\Entity\ActorContact((string) $me->getUserIdentifier(), 'Mairie d\'exemple', 'https://www.service-public.fr/');
            $fake->setCategory($lettre->getActorCategory() ?: 'commune');
            try {
                $mailer->sendToActor($lettre, $fake, true, (string) $me->getUserIdentifier());
                $this->addFlash('success', 'Test envoyé à ' . $me->getUserIdentifier() . ' (le lien de désinscription du test ne fonctionne pas).');
            } catch (\Throwable $e) {
                $this->addFlash('error', "L'envoi du test a échoué : " . $this->reason($e));
            }

            return $this->redirectToRoute('admin_lettre_show', ['id' => $lettre->getId()]);
        }
        // Le test part uniquement vers l'adresse de l'administrateur connecte.
        $probe = (new NewsletterSubscriber())->setEmail((string) $me->getUserIdentifier());
        $em->persist($probe);
        $em->flush();
        try {
            $mailer->sendLettre($lettre, $probe, true);
            $this->addFlash('success', 'Lettre de test envoyée à ' . $me->getUserIdentifier() . '.');
        } catch (\Throwable $e) {
            $this->addFlash('error', "L'envoi du test a échoué : " . $this->reason($e));
        } finally {
            $em->remove($probe);
            $em->flush();
        }

        return $this->redirectToRoute('admin_lettre_show', ['id' => $lettre->getId()]);
    }

    #[Route('/{id<\d+>}/envoyer', name: 'admin_lettre_queue', methods: ['POST'])]
    public function queue(Request $request, Lettre $lettre, LettreRepository $repo, LettreMailer $mailer, EntityManagerInterface $em): Response
    {
        $this->checkCsrf($request, 'lettre_queue' . $lettre->getId());
        if (!$lettre->isEditable()) {
            $this->addFlash('error', 'Cette lettre a déjà été mise en file.');

            return $this->redirectToRoute('admin_lettre_show', ['id' => $lettre->getId()]);
        }
        if ($problem = $mailer->readinessProblem()) {
            $this->addFlash('error', $problem);

            return $this->redirectToRoute('admin_lettre_show', ['id' => $lettre->getId()]);
        }
        $count = $repo->countFor($lettre);
        if (0 === $count) {
            $this->addFlash('error', "Aucun abonné n'a confirmé son adresse : rien à envoyer.");

            return $this->redirectToRoute('admin_lettre_show', ['id' => $lettre->getId()]);
        }
        // Garde-fou : il faut retaper le nombre exact de destinataires.
        if (trim((string) $request->request->get('confirm_count')) !== (string) $count) {
            $this->addFlash('error', sprintf('Confirmation incorrecte : tapez exactement %d pour lancer l\'envoi.', $count));

            return $this->redirectToRoute('admin_lettre_show', ['id' => $lettre->getId()]);
        }
        $lettre->setStatus(Lettre::STATUS_QUEUED)->setQueuedAt(new \DateTimeImmutable())->setTargetCount($count);
        $em->flush();
        $this->addFlash('success', sprintf('Lettre mise en file pour %d abonné(s). L\'envoi se fait par lots (commande app:lettre:envoyer).', $count));

        return $this->redirectToRoute('admin_lettre_show', ['id' => $lettre->getId()]);
    }

    #[Route('/{id<\d+>}/annuler', name: 'admin_lettre_cancel', methods: ['POST'])]
    public function cancel(Request $request, Lettre $lettre, EntityManagerInterface $em): Response
    {
        $this->checkCsrf($request, 'lettre_cancel' . $lettre->getId());
        if (in_array($lettre->getStatus(), [Lettre::STATUS_QUEUED, Lettre::STATUS_SENDING], true)) {
            $lettre->setStatus(Lettre::STATUS_CANCELLED)->setFinishedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', "Envoi annulé. Les messages déjà partis ne peuvent pas être rappelés.");
        }

        return $this->redirectToRoute('admin_lettre_show', ['id' => $lettre->getId()]);
    }

    #[Route('/{id<\d+>}/supprimer', name: 'admin_lettre_delete', methods: ['POST'])]
    public function delete(Request $request, Lettre $lettre, EntityManagerInterface $em): Response
    {
        $this->checkCsrf($request, 'lettre_delete' . $lettre->getId());
        if (in_array($lettre->getStatus(), [Lettre::STATUS_DRAFT, Lettre::STATUS_CANCELLED], true)) {
            $em->remove($lettre);
            $em->flush();
            $this->addFlash('success', 'Lettre supprimée.');
        } else {
            $this->addFlash('error', "Seuls un brouillon ou un envoi annulé peuvent être supprimés.");
        }

        return $this->redirectToRoute('admin_lettre_index');
    }

    private function edit(Request $request, Lettre $lettre, EntityManagerInterface $em, ValidatorInterface $validator, bool $isNew): Response
    {
        $errors = [];
        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'lettre_edit');
            $lettre->setSubject((string) $request->request->get('subject'))->setBody(str_replace("\r\n", "\n", (string) $request->request->get('body')))->setAudience((string) $request->request->get('audience'))->setLocale((string) $request->request->get('locale') ?: null)->setTarget((string) $request->request->get('actor_category') ?: null, strtoupper(trim((string) $request->request->get('actor_dept'))) ?: null);
            foreach ($validator->validate($lettre) as $v) {
                $errors[] = $v->getMessage();
            }
            if (!$errors) {
                if ($isNew) {
                    $em->persist($lettre);
                }
                $em->flush();
                $this->addFlash('success', 'Brouillon enregistré. Envoyez-vous un test avant de lancer l\'envoi.');

                return $this->redirectToRoute('admin_lettre_show', ['id' => $lettre->getId()]);
            }
        }

        return $this->render('admin/lettre/form.html.twig', ['lettre' => $lettre, 'errors' => $errors, 'isNew' => $isNew]);
    }

    /** Cause technique lisible par l'administrateur (jamais de secret : on garde la classe et le debut du message). */
    private function reason(\Throwable $e): string
    {
        $msg = preg_replace('~://[^@\s]*@~', '://***@', $e->getMessage()) ?? '';

        return (new \ReflectionClass($e))->getShortName() . ' : ' . mb_substr($msg, 0, 300);
    }

    private function checkCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
