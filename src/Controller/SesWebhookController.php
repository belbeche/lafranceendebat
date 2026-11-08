<?php

namespace App\Controller;

use App\Repository\NewsletterSubscriberRepository;
use App\Service\Ses\SnsVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recoit les rebonds et plaintes d'Amazon SES via SNS. Rien n'est pris en compte sans signature valide
 * et sans correspondance avec la rubrique SNS configuree (SES_SNS_TOPIC_ARN).
 */
class SesWebhookController extends AbstractController
{
    #[Route('/webhook/ses', name: 'app_ses_webhook', methods: ['POST'])]
    public function __invoke(Request $request, SnsVerifier $sns, NewsletterSubscriberRepository $subs, EntityManagerInterface $em, LoggerInterface $logger): Response
    {
        $raw = $request->getContent();
        if (strlen($raw) > 100000 || !$sns->isConfigured()) {
            return new Response('', 404);
        }
        $m = json_decode($raw, true);
        if (!is_array($m) || !$sns->verify($m)) {
            return new Response('', 403);
        }
        $type = $m['Type'];
        if ($type === 'SubscriptionConfirmation') {
            $sns->confirmSubscription((string) ($m['SubscribeURL'] ?? ''));

            return new Response('ok');
        }
        if ($type !== 'Notification') {
            return new Response('ok');
        }
        $event = json_decode((string) $m['Message'], true);
        if (!is_array($event)) {
            return new Response('ok');
        }
        $kind = $event['notificationType'] ?? $event['eventType'] ?? '';
        $now = new \DateTimeImmutable();
        $emails = [];
        $hard = false;
        if ($kind === 'Bounce') {
            $hard = ($event['bounce']['bounceType'] ?? '') === 'Permanent';
            foreach ($event['bounce']['bouncedRecipients'] ?? [] as $r) {
                $emails[] = $r['emailAddress'] ?? '';
            }
        } elseif ($kind === 'Complaint') {
            foreach ($event['complaint']['complainedRecipients'] ?? [] as $r) {
                $emails[] = $r['emailAddress'] ?? '';
            }
        } else {
            return new Response('ok');
        }
        foreach (array_unique(array_filter(array_map(static fn ($e) => mb_strtolower(trim((string) $e)), $emails))) as $email) {
            $actor = $em->getRepository(\App\Entity\ActorContact::class)->findOneBy(['email' => $email]);
            if ($actor) {
                if ($kind === 'Complaint') {
                    $actor->unsubscribe(\App\Entity\ActorContact::COMPLAINT);
                } elseif ($hard) {
                    $actor->unsubscribe(\App\Entity\ActorContact::BOUNCED);
                }
            }
            $s = $subs->findOneBy(['email' => $email]);
            if (!$s) {
                continue;
            }
            if ($kind === 'Complaint') {
                $s->setMarkedAsSpamAt($now);
                $s->setUnsubscribedAt($s->getUnsubscribedAt() ?? $now);
            } elseif ($hard) {
                $s->setBouncedAt($now);
            }
        }
        $em->flush();
        $logger->info('SES {kind} traite pour {n} destinataire(s)', ['kind' => $kind, 'n' => count($emails)]);

        return new Response('ok');
    }
}
