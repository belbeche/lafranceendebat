<?php

namespace App\Controller;

use App\Entity\ContentTranslation;
use App\Entity\Discussion;
use App\Entity\DiscussionReply;
use App\Entity\Proposition;
use App\Entity\Revendication;
use App\Entity\User;
use App\Service\ContentModerator;
use App\Service\ContentTranslator;
use App\Service\NotificationMailer;
use App\Service\RevisionRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Modifier ou retirer ses propres contributions : propositions, discussions, reponses, revendications.
 * Meme modele que les arguments : filtre de moderation a chaque enregistrement, delai de grace de 5 minutes,
 * mention « modifie », retrait par masquage (jamais d'effacement), moderateurs autorises a corriger.
 */
#[IsGranted('ROLE_USER')]
class ContentEditController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ContentModerator $moderator,
        private readonly ValidatorInterface $validator,
        private readonly RevisionRecorder $revisions,
    ) {
    }

    // ---------- Propositions ----------
    #[Route('/propositions/{id}/modifier', name: 'app_proposition_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function proposition(int $id, Request $request): Response
    {
        $p = $this->em->getRepository(Proposition::class)->find($id) ?? throw $this->createNotFoundException();
        $this->assertCanEdit($p->getAuthor(), Proposition::STATUS_WITHDRAWN === $p->getStatus() || Proposition::STATUS_REJECTED === $p->getStatus());
        $fields = [
            ['name' => 'title', 'label' => 'Titre', 'type' => 'input', 'value' => (string) $p->getTitle(), 'max' => 180],
            ['name' => 'theme', 'label' => 'Thème', 'type' => 'select', 'value' => (string) $p->getTheme(), 'options' => Proposition::THEMES],
            ['name' => 'description', 'label' => 'Mesure proposée', 'type' => 'textarea', 'value' => (string) $p->getDescription(), 'max' => 5000, 'rows' => 9],
            ['name' => 'sourceUrl', 'label' => 'Source (lien https, facultatif)', 'type' => 'input', 'value' => (string) $p->getSourceUrl(), 'max' => 255, 'required' => false],
        ];

        return $this->handle($request, 'prop_edit' . $id, $fields, $p, $p->getCreatedAt(), 'Modifier ma proposition', function (array $v) use ($p): void {
            $p->setTitle($v['title'])->setTheme($v['theme'])->setDescription($v['description'])->setSourceUrl($v['sourceUrl']);
        }, fn () => $this->redirectToRoute('app_proposition_show', ['id' => $p->getId()]), ['title', 'description']);
    }

    #[Route('/propositions/{id}/retirer', name: 'app_proposition_withdraw', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function propositionWithdraw(int $id, Request $request): Response
    {
        $p = $this->em->getRepository(Proposition::class)->find($id) ?? throw $this->createNotFoundException();

        return $this->withdraw($request, 'prop_withdraw' . $id, $p->getAuthor(), fn () => $p->setStatus(Proposition::STATUS_WITHDRAWN), 'app_my_propositions', [], 'Votre proposition est retirée.');
    }

    // ---------- Discussions ----------
    #[Route('/communaute/{id}/modifier', name: 'app_community_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function discussion(int $id, Request $request): Response
    {
        $d = $this->em->getRepository(Discussion::class)->find($id) ?? throw $this->createNotFoundException();
        $this->assertCanEdit($d->getAuthor(), Proposition::STATUS_WITHDRAWN === $d->getStatus() || Proposition::STATUS_REJECTED === $d->getStatus());
        $fields = [
            ['name' => 'title', 'label' => 'Titre', 'type' => 'input', 'value' => (string) $d->getTitle(), 'max' => 160],
            ['name' => 'body', 'label' => 'Votre message', 'type' => 'textarea', 'value' => (string) $d->getBody(), 'max' => 4000, 'rows' => 9],
        ];

        return $this->handle($request, 'disc_edit' . $id, $fields, $d, $d->getCreatedAt(), 'Modifier mon message', function (array $v) use ($d): void {
            $d->setTitle($v['title'])->setBody($v['body']);
        }, fn () => $this->redirectToRoute('app_community_show', ['id' => $d->getId()]), ['title', 'body']);
    }

    #[Route('/communaute/{id}/retirer', name: 'app_community_withdraw', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function discussionWithdraw(int $id, Request $request): Response
    {
        $d = $this->em->getRepository(Discussion::class)->find($id) ?? throw $this->createNotFoundException();

        return $this->withdraw($request, 'disc_withdraw' . $id, $d->getAuthor(), fn () => $d->setStatus(Proposition::STATUS_WITHDRAWN), 'app_community', [], 'Votre message est retiré.');
    }

    // ---------- Reponses ----------
    #[Route('/communaute/reponse/{id}/modifier', name: 'app_community_reply_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function reply(int $id, Request $request): Response
    {
        $r = $this->em->getRepository(DiscussionReply::class)->find($id) ?? throw $this->createNotFoundException();
        $this->assertCanEdit($r->getAuthor(), Proposition::STATUS_WITHDRAWN === $r->getStatus() || Proposition::STATUS_REJECTED === $r->getStatus());
        $fields = [['name' => 'body', 'label' => 'Votre réponse', 'type' => 'textarea', 'value' => (string) $r->getBody(), 'max' => 6000, 'rows' => 8]];
        if (null !== $r->getReformulation()) {
            $fields[] = ['name' => 'reformulation', 'label' => 'Ce que je comprends de la position à laquelle je réponds', 'type' => 'textarea', 'value' => (string) $r->getReformulation(), 'max' => 800, 'rows' => 3, 'required' => false];
        }

        return $this->handle($request, 'reply_edit' . $id, $fields, $r, $r->getCreatedAt(), 'Modifier ma réponse', function (array $v) use ($r): void {
            $r->setBody($v['body']);
            if (array_key_exists('reformulation', $v)) {
                $r->setReformulation($v['reformulation']);
            }
        }, fn () => $this->redirectToRoute('app_community_show', ['id' => $r->getDiscussion()?->getId(), '_fragment' => 'reponse-' . $r->getId()]), ['body', 'reformulation']);
    }

    #[Route('/communaute/reponse/{id}/retirer', name: 'app_community_reply_withdraw', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function replyWithdraw(int $id, Request $request): Response
    {
        $r = $this->em->getRepository(DiscussionReply::class)->find($id) ?? throw $this->createNotFoundException();

        return $this->withdraw($request, 'reply_withdraw' . $id, $r->getAuthor(), fn () => $r->setStatus(Proposition::STATUS_WITHDRAWN), 'app_community_show', ['id' => $r->getDiscussion()?->getId()], 'Votre réponse est retirée.');
    }

    // ---------- Revendications ----------
    #[Route('/revendications/{slug}/modifier', name: 'app_revendication_edit', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET', 'POST'])]
    public function revendication(string $slug, Request $request, NotificationMailer $notifier): Response
    {
        $c = $this->em->getRepository(Revendication::class)->findOneBy(['slug' => $slug]) ?? throw $this->createNotFoundException();
        $this->assertCanEdit($c->getSubmittedBy(), false);
        $mod = $this->isGranted('ROLE_MODERATOR');
        $fields = [
            ['name' => 'title', 'label' => 'Titre', 'type' => 'input', 'value' => (string) $c->getTitle(), 'max' => 200],
            ['name' => 'summary', 'label' => 'Ce qui est dénoncé ou demandé', 'type' => 'textarea', 'value' => (string) $c->getSummary(), 'max' => 5000, 'rows' => 9],
            ['name' => 'sourceLabel', 'label' => 'Nom de la source', 'type' => 'input', 'value' => (string) $c->getSourceLabel(), 'max' => 255, 'required' => false],
            ['name' => 'sourceUrl', 'label' => 'Lien de la source (https)', 'type' => 'input', 'value' => (string) $c->getSourceUrl(), 'max' => 255, 'required' => false],
        ];
        $note = !$mod && $c->isPublished() ? 'Cette demande est publiée : après modification, elle repasse en relecture avant de réapparaître, car elle concerne des faits et des tiers.' : null;

        return $this->handle($request, 'claim_edit' . $c->getId(), $fields, $c, $c->getUpdatedAt(), 'Modifier ma demande', function (array $v) use ($c, $mod, $notifier): void {
            $c->setTitle($v['title'])->setSummary($v['summary'])->setSourceLabel($v['sourceLabel'] ?: null)->setSourceUrl($v['sourceUrl'] ?: null)->setUpdatedAt(new \DateTimeImmutable());
            if (!$mod && $c->isPublished()) {
                $c->setIsPublished(false);
                $notifier->claimSubmitted($c);
            }
        }, fn () => $this->redirectToRoute($c->isPublished() ? 'app_revendication_show' : 'app_my_requests', $c->isPublished() ? ['slug' => $c->getSlug()] : []), ['title', 'summary'], $note, function (array $v) use ($c): array {
            $e = [];
            if ($v['sourceUrl'] !== '' && !preg_match('#^https://[^\s]+\.[^\s]+$#i', $v['sourceUrl'])) {
                $e[] = 'Le lien de la source doit être en https.';
            }
            if (mb_strlen($v['summary']) < 40) {
                $e[] = 'Décrivez la demande en 40 caractères minimum.';
            }

            return $e;
        });
    }

    // ---------- Mecanique commune ----------
    private function assertCanEdit(?User $owner, bool $hidden): void
    {
        $me = $this->getUser();
        $mine = $owner && $me instanceof User && $owner->getId() === $me->getId();
        if ((!$mine && !$this->isGranted('ROLE_MODERATOR')) || ($hidden && !$this->isGranted('ROLE_MODERATOR'))) {
            throw $this->createAccessDeniedException();
        }
    }

    /** @param list<array<string,mixed>> $fields */
    private function handle(Request $request, string $tokenId, array $fields, object $entity, \DateTimeImmutable $createdAt, string $title, callable $apply, callable $done, array $moderated, ?string $note = null, ?callable $extra = null): Response
    {
        $errors = [];
        $values = [];
        foreach ($fields as $f) {
            $values[$f['name']] = $f['value'];
        }
        if ($request->isMethod('POST')) {
            foreach ($fields as $f) {
                $values[$f['name']] = trim((string) $request->request->get($f['name'], ''));
            }
            if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
                $errors[] = 'Session expirée, réessayez.';
            }
            $text = implode("\n", array_map(fn ($k) => $values[$k] ?? '', $moderated));
            if ($this->moderator->analyze($text) !== []) {
                $errors[] = 'Le texte contient une insulte, une menace, une expression haineuse ou une donnée personnelle. Reformulez calmement.';
            }
            if ($extra) {
                $errors = array_merge($errors, $extra($values));
            }
            $old = [];
            foreach ($fields as $f) {
                $old[$f['name']] = (string) $f['value'];
            }
            if (!$errors) {
                $apply($values);
                foreach ($this->validator->validate($entity) as $violation) {
                    $errors[] = $violation->getMessage();
                }
            }
            if (!$errors) {
                $this->revisions->record($this->typeOf($entity), (int) $entity->getId(), $old, $createdAt, $this->isGranted('ROLE_MODERATOR') && !$this->isOwner($entity), $values);
                $entity->markEdited($createdAt);
                $this->purgeTranslations($entity);
                $this->em->flush();
                $this->addFlash('success', 'Modification enregistrée.');

                return $done();
            }
            $this->em->clear();
        }
        foreach ($fields as $i => $f) {
            $fields[$i]['value'] = $values[$f['name']] ?? $f['value'];
        }

        return $this->render('edit/content.html.twig', ['title' => $title, 'fields' => $fields, 'errors' => $errors, 'token' => $tokenId, 'note' => $note, 'back' => $request->headers->get('referer')]);
    }

    private function withdraw(Request $request, string $tokenId, ?User $owner, callable $do, string $route, array $params, string $message): Response
    {
        $me = $this->getUser();
        if (!$owner || !$me instanceof User || $owner->getId() !== $me->getId()) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée, réessayez.');

            return $this->redirectToRoute($route, $params);
        }
        $do();
        $this->em->flush();
        $this->addFlash('success', $message . ' Les modérateurs gardent une trace en cas de litige.');

        return $this->redirectToRoute($route, $params);
    }

    private function typeOf(object $e): string
    {
        return match (true) {
            $e instanceof Proposition => 'proposition',
            $e instanceof Discussion => 'discussion',
            $e instanceof DiscussionReply => 'reponse',
            default => 'revendication',
        };
    }

    private function isOwner(object $e): bool
    {
        $owner = $e instanceof Revendication ? $e->getSubmittedBy() : $e->getAuthor();
        $me = $this->getUser();

        return $owner && $me instanceof User && $owner->getId() === $me->getId();
    }

    /** Une modification rend les traductions enregistrees obsoletes : elles sont supprimees et regenerees a la demande. */
    private function purgeTranslations(object $entity): void
    {
        $type = ContentTranslator::typeOf($entity);
        if ($type && method_exists($entity, 'getId') && $entity->getId()) {
            $this->em->createQuery('DELETE FROM ' . ContentTranslation::class . ' t WHERE t.type = :t AND t.refId = :i')->setParameters(['t' => $type, 'i' => $entity->getId()])->execute();
        }
    }
}
