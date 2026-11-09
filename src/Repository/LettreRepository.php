<?php

namespace App\Repository;

use App\Entity\Lettre;
use App\Entity\NewsletterSubscriber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Lettre> */
class LettreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lettre::class);
    }

    /** Lettre a traiter en priorite par la commande d'envoi. */
    public function findNextToSend(): ?Lettre
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.status IN (:s)')->setParameter('s', [Lettre::STATUS_QUEUED, Lettre::STATUS_SENDING])
            ->orderBy('l.id', 'ASC')->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }

    /** Abonnes eligibles : adresse confirmee, actifs, ni desinscrits, ni rejetes (bounce), ni marques spam. */
    private function localized(\Doctrine\ORM\QueryBuilder $qb, ?string $locale): \Doctrine\ORM\QueryBuilder
    {
        return $locale ? $qb->andWhere('s.locale = :loc')->setParameter('loc', $locale) : $qb;
    }

    private function eligible(): \Doctrine\ORM\QueryBuilder
    {
        return $this->getEntityManager()->createQueryBuilder()->select('s')->from(NewsletterSubscriber::class, 's')
            ->andWhere('s.confirmedAt IS NOT NULL')
            ->andWhere('s.isActive = true')
            ->andWhere('s.unsubscribedAt IS NULL')
            ->andWhere('s.bouncedAt IS NULL')
            ->andWhere('s.markedAsSpamAt IS NULL');
    }

    public function countEligible(?string $locale = null): int
    {
        return (int) $this->localized($this->eligible(), $locale)->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();
    }

    /** Nombre de destinataires selon l'audience de la lettre (abonnes confirmes ou acteurs publics). */
    public function countFor(Lettre $l): int
    {
        return $l->isForActors() ? $this->getEntityManager()->getRepository(\App\Entity\ActorContact::class)->countEligible($l->isInvitation(), $l->getLocale(), $l->getActorCategory(), $l->getActorDept()) : $this->countEligible($l->getLocale());
    }

    /** Etapes de filtrage et effectif restant, pour comprendre un "0 destinataire" (voir l'ecran d'une lettre). @return list<array{label:string,count:int}> */
    public function diagnose(Lettre $l): array
    {
        if ($l->isForActors()) {
            return $this->getEntityManager()->getRepository(\App\Entity\ActorContact::class)->diagnose($l->isInvitation(), $l->getLocale(), $l->getActorCategory(), $l->getActorDept());
        }
        $total = (int) $this->getEntityManager()->createQueryBuilder()->select('COUNT(s.id)')->from(NewsletterSubscriber::class, 's')->getQuery()->getSingleScalarResult();
        $steps = [['Abonnés en base', $total], ['Confirmés, actifs, non désinscrits', $this->countEligible()]];
        if ($l->getLocale()) {
            $steps[] = ['Langue « ' . $l->getLocale() . ' »', $this->countEligible($l->getLocale())];
        }

        return array_map(static fn (array $r): array => ['label' => $r[0], 'count' => $r[1]], $steps);
    }

    /** @return list<NewsletterSubscriber|\App\Entity\ActorContact> */
    public function nextFor(Lettre $l, int $afterId, int $limit): array
    {
        return $l->isForActors() ? $this->getEntityManager()->getRepository(\App\Entity\ActorContact::class)->nextRecipients($afterId, $limit, $l->isInvitation(), $l->getLocale(), $l->getActorCategory(), $l->getActorDept()) : $this->nextRecipients($afterId, $limit, $l->getLocale());
    }

    /** @return NewsletterSubscriber[] */
    public function nextRecipients(int $afterId, int $limit, ?string $locale = null): array
    {
        return $this->localized($this->eligible(), $locale)->andWhere('s.id > :c')->setParameter('c', $afterId)
            ->orderBy('s.id', 'ASC')->setMaxResults($limit)->getQuery()->getResult();
    }
}
