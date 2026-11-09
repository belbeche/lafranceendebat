<?php

namespace App\Controller\Admin;

use App\Entity\ActorContact;
use App\Entity\Lettre;
use App\Entity\NewsletterSubscriber;
use App\Repository\ActorContactRepository;
use App\Service\ActorImporter;
use App\Service\Lettre\LettreMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Envoi ciblé : quelques adresses professionnelles saisies à la main (partenariat, demande d'information).
 * Mêmes garde-fous que le démarchage de masse : adresse professionnelle publiée, source publique https,
 * désinscription en un clic, exclusion définitive des désinscrits, pas de doublon par organisation, confirmation explicite.
 */
#[Route('/admin/lettres/ciblee')]
#[IsGranted('ROLE_ADMIN')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class TargetedLetterController extends AbstractController
{
    public const MAX_RECIPIENTS = 10;

    public const PARTNERSHIP_SUBJECT = 'Proposition de partenariat : La France en Débat et MonVote2027';
    public const PARTNERSHIP_BODY = "Bonjour,\n\nJe suis Walid Belbeche, développeur indépendant et éditeur de La France en Débat (https://lafranceendebat.fr), une plateforme citoyenne qui permet de débattre sans violence, de déposer des propositions et de suivre les engagements publics, en s'appuyant uniquement sur des sources officielles.\n\nNos projets sont proches : vous aidez les citoyens à comparer les positions pour 2027, nous leur donnons un espace pour en discuter et proposer. Je pense qu'ils se complètent.\n\nJe vous propose d'échanger sur un partenariat simple, sans engagement :\n- des liens croisés entre nos deux sites ;\n- un renvoi de nos débats vers vos fiches de candidats, avec mention de votre site comme source ;\n- si vous le souhaitez, une réutilisation de vos données ouvertes, dans le respect de votre licence.\n\nPour cela, une précision m'aiderait : vos contenus sont publiés « sous licence ouverte ». Pourriez-vous m'indiquer laquelle, et si un export de vos données est disponible ? Je n'ai rien repris sans votre accord et je ne le ferai pas.\n\nSi l'idée vous intéresse, répondez simplement à ce message. Sinon, aucun problème : vous ne recevrez rien d'autre.\n\nCordialement,\nWalid Belbeche\nLa France en Débat";

    #[Route('', name: 'admin_lettre_ciblee', methods: ['GET', 'POST'])]
    public function index(Request $request, ActorImporter $importer, ActorContactRepository $contacts, LettreMailer $mailer, EntityManagerInterface $em): Response
    {
        $subject = (string) $request->request->get('subject', $request->query->get('modele') === 'partenariat' || !$request->isMethod('POST') ? self::PARTNERSHIP_SUBJECT : '');
        $body = str_replace("\r\n", "\n", (string) $request->request->get('body', self::PARTNERSHIP_BODY));
        $raw = (string) $request->request->get('recipients', "contact@monvote2027.fr;MonVote2027;Contact éditeur;https://monvote2027.fr/mentions-legales");
        $action = (string) $request->request->get('action', '');
        $rows = [];
        $result = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('lettre_ciblee', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $rows = $this->analyse($raw, $importer, $contacts, $em);
            $ready = array_values(array_filter($rows, static fn (array $r): bool => null === $r['problem']));
            $errors = [];
            if ('' === trim($subject) || '' === trim($body)) {
                $errors[] = 'Objet et contenu sont obligatoires.';
            }
            if (count($rows) > self::MAX_RECIPIENTS) {
                $errors[] = sprintf('Maximum %d destinataires par envoi ciblé. Pour davantage, utilisez une lettre.', self::MAX_RECIPIENTS);
            }
            if ($problem = ('check' === $action ? null : $mailer->readinessProblem())) {
                $errors[] = $problem;
            }
            if ('send' === $action && !$request->request->get('confirm')) {
                $errors[] = 'Cochez la confirmation pour envoyer.';
            }
            if ('send' === $action && 0 === count($ready)) {
                $errors[] = 'Aucun destinataire valide.';
            }
            foreach ($errors as $e) {
                $this->addFlash('error', $e);
            }
            if (!$errors && 'test' === $action) {
                $me = (string) $this->getUser()->getUserIdentifier();
                $fake = new ActorContact($me, 'Organisation d\'exemple', 'https://www.service-public.fr/');
                try {
                    $mailer->sendToActor($this->letter($subject, $body), $fake, true, $me);
                    $this->addFlash('success', 'Test envoyé à ' . $me . '.');
                } catch (\Throwable $e) {
                    $this->addFlash('error', "L'envoi du test a échoué : " . mb_substr(preg_replace('~://[^@\s]*@~', '://***@', $e->getMessage()) ?? '', 0, 300));
                }
            }
            if (!$errors && 'send' === $action) {
                $result = $this->send($ready, $this->letter($subject, $body), $mailer, $em);
                $rows = $this->analyse($raw, $importer, $contacts, $em);
            }
        }

        return $this->render('admin/lettre/ciblee.html.twig', [
            'subject' => $subject, 'body' => $body, 'recipients' => $raw, 'rows' => $rows, 'result' => $result,
            'max' => self::MAX_RECIPIENTS, 'problem' => $mailer->readinessProblem(),
        ]);
    }

    private function letter(string $subject, string $body): Lettre
    {
        return (new Lettre())->setSubject($subject)->setBody($body)->setAudience(Lettre::AUD_ACTORS);
    }

    /** @return list<array{email:string,organisation:string,role:?string,source:string,problem:?string}> */
    private function analyse(string $raw, ActorImporter $importer, ActorContactRepository $contacts, EntityManagerInterface $em): array
    {
        $rows = [];
        $seen = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            $p = array_map('trim', explode(';', $line));
            $email = mb_strtolower($p[0] ?? '');
            $r = ['email' => $email, 'organisation' => $p[1] ?? '', 'fonction' => $p[2] ?? null, 'source_url' => $p[3] ?? '', 'categorie' => 'autre'];
            $problem = $importer->check($email, $r);
            if (!$problem && isset($seen[$email])) {
                $problem = 'Adresse en double dans la liste.';
            }
            $seen[$email] = true;
            if (!$problem) {
                $existing = $em->getRepository(ActorContact::class)->findOneBy(['email' => $email]);
                $sub = $em->getRepository(NewsletterSubscriber::class)->findOneBy(['email' => $email]);
                if ($existing && ActorContact::ACTIVE !== $existing->getStatus()) {
                    $problem = 'Désinscrit, en rebond ou en plainte : exclu définitivement.';
                } elseif ($sub && ($sub->getUnsubscribedAt() || $sub->getBouncedAt() || $sub->getMarkedAsSpamAt())) {
                    $problem = 'Adresse désinscrite ou en rebond : exclue.';
                } elseif ($existing && $existing->getLastContactedAt() && $existing->getLastContactedAt() >= new \DateTimeImmutable('-' . ActorContactRepository::MIN_DAYS_BETWEEN_CONTACTS . ' days')) {
                    $problem = 'Déjà contacté ces 7 derniers jours.';
                } elseif ($existing ? $contacts->organisationAlreadyReached($existing, false) : $contacts->organisationAlreadyReached(new ActorContact($email, (string) $r['organisation'], $r['source_url']), false)) {
                    $problem = 'Cette organisation a déjà été contactée ces 7 derniers jours.';
                }
            }
            $rows[] = ['email' => $email, 'organisation' => (string) $r['organisation'], 'role' => $r['fonction'] ?: null, 'source' => (string) $r['source_url'], 'problem' => $problem];
        }

        return $rows;
    }

    /** @param list<array{email:string,organisation:string,role:?string,source:string,problem:?string}> $ready */
    private function send(array $ready, Lettre $lettre, LettreMailer $mailer, EntityManagerInterface $em): array
    {
        $sent = 0;
        $failed = [];
        foreach ($ready as $r) {
            $a = $em->getRepository(ActorContact::class)->findOneBy(['email' => $r['email']]);
            if (!$a) {
                $a = new ActorContact($r['email'], mb_substr($r['organisation'], 0, 190), $r['source']);
                $a->setRole($r['role'])->setCategory('autre');
                $em->persist($a);
            }
            $em->flush();
            $prev = [$a->getLastContactedAt(), $a->getContactCount(), $a->getInvitedAt()];
            $a->markContacted();
            $em->flush();
            try {
                $mailer->sendToActor($lettre, $a);
                ++$sent;
            } catch (\Throwable $e) {
                $a->restoreContact(...$prev);
                $em->flush();
                $failed[] = $r['email'];
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
