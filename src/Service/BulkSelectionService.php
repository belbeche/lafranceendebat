<?php

// src/Service/BulkSelectionService.php

namespace App\Service;

use App\Entity\NewsletterCampaign;
use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service métier « BulkSelectionService ».
 */
class BulkSelectionService
{
    private const BATCH_SIZE = 1000;
    
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NewsletterSubscriberRepository $subscriberRepository,
        private LoggerInterface $logger
    ) {}
    
    /**
     * Sélectionne tous les abonnés actifs pour une campagne (optimisé pour 200K+)
     */
    public function selectAllActiveSubscribers(NewsletterCampaign $campaign): int
    {
        $this->logger->info('=== DÉBUT SÉLECTION MASSE TOUS LES ABONNÉS ===');
        
        try {
            // 1. Compter le nombre total d'abonnés actifs
            $totalActive = $this->subscriberRepository->countActiveSubscribers();
            $this->logger->info("Total abonnés actifs: $totalActive");
            
            if ($totalActive === 0) {
                throw new \Exception('Aucun abonné actif trouvé');
            }
            
            // 2. Utiliser une approche paginée pour éviter les problèmes mémoire
            $offset = 0;
            $addedCount = 0;
            $batchNumber = 1;
            
            do {
                // Désactiver les logs SQL pour optimiser les performances
                $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);
                
                // Charger un batch d'abonnés avec seulement les IDs
                $batchIds = $this->subscriberRepository
                    ->createQueryBuilder('s')
                    ->select('s.id')
                    ->where('s.isActive = true')
                    ->orderBy('s.id', 'ASC')
                    ->setFirstResult($offset)
                    ->setMaxResults(self::BATCH_SIZE)
                    ->getQuery()
                    ->getScalarResult();
                
                $ids = array_column($batchIds, 'id');
                
                if (empty($ids)) {
                    break;
                }
                
                $this->logger->debug("Batch $batchNumber: " . count($ids) . " IDs récupérés");
                
                // Ajouter les abonnés à la campagne
                $addedInBatch = $this->addSubscribersByIds($campaign, $ids);
                $addedCount += $addedInBatch;
                
                $this->logger->debug("Batch $batchNumber: $addedInBatch abonnés ajoutés");
                
                // Commit et clear pour libérer la mémoire
                $this->entityManager->flush();
                $this->entityManager->clear();
                
                $offset += self::BATCH_SIZE;
                $batchNumber++;
                
                // Mettre à jour la progression dans les logs
                $progress = round(($offset / $totalActive) * 100, 1);
                $this->logger->info("Progression: $progress% ($addedCount/$totalActive)");
                
            } while (!empty($ids) && $offset < $totalActive);
            
            // 3. Mettre à jour le compte final
            $campaign->setTotalRecipients($addedCount);
            $this->entityManager->persist($campaign);
            $this->entityManager->flush();
            
            $this->logger->info("=== SÉLECTION TERMINÉE ===");
            $this->logger->info("Total ajouté: $addedCount abonnés");
            
            return $addedCount;
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur sélection masse: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Ajoute des abonnés par leurs IDs (optimisé)
     */
    private function addSubscribersByIds(NewsletterCampaign $campaign, array $subscriberIds): int
    {
        if (empty($subscriberIds)) {
            return 0;
        }
        
        // Charger les entités directement avec un DQL optimisé
        $query = $this->entityManager->createQuery(
            'SELECT s FROM App\Entity\NewsletterSubscriber s 
             WHERE s.id IN (:ids) AND s.isActive = true'
        )->setParameter('ids', $subscriberIds);
        
        $subscribers = $query->getResult();
        $addedCount = 0;
        
        foreach ($subscribers as $subscriber) {
            if (!$campaign->getSubscribers()->contains($subscriber)) {
                $campaign->addSubscriber($subscriber);
                $addedCount++;
            }
        }
        
        return $addedCount;
    }
    
    /**
     * Sélectionne des abonnés par filtres (optimisé)
     */
    public function selectFilteredSubscribers(
        NewsletterCampaign $campaign,
        array $criteria = []
    ): int {
        $this->logger->info('=== DÉBUT SÉLECTION FILTRÉE ===');
        $this->logger->info('Critères: ' . json_encode($criteria));
        
        $offset = 0;
        $addedCount = 0;
        $batchNumber = 1;
        
        // Construire la requête de base
        $qb = $this->subscriberRepository->createQueryBuilder('s')
            ->select('s.id')
            ->where('s.isActive = true');
        
        // Appliquer les filtres
        if (!empty($criteria['search'])) {
            $search = '%' . $criteria['search'] . '%';
            $qb->andWhere('s.email LIKE :search OR s.firstName LIKE :search OR s.lastName LIKE :search')
               ->setParameter('search', $search);
        }
        
        if (!empty($criteria['date_from'])) {
            $qb->andWhere('s.subscribedAt >= :date_from')
               ->setParameter('date_from', $criteria['date_from']);
        }
        
        if (!empty($criteria['date_to'])) {
            $qb->andWhere('s.subscribedAt <= :date_to')
               ->setParameter('date_to', $criteria['date_to']);
        }
        
        $qb->orderBy('s.id', 'ASC');
        
        do {
            // Désactiver les logs SQL
            $this->entityManager->getConnection()->getConfiguration()->setSQLLogger(null);
            
            // Obtenir les IDs du batch
            $batchQuery = clone $qb;
            $batchIds = $batchQuery
                ->setFirstResult($offset)
                ->setMaxResults(self::BATCH_SIZE)
                ->getQuery()
                ->getScalarResult();
            
            $ids = array_column($batchIds, 'id');
            
            if (empty($ids)) {
                break;
            }
            
            $this->logger->debug("Batch $batchNumber: " . count($ids) . " IDs");
            
            // Ajouter les abonnés
            $addedInBatch = $this->addSubscribersByIds($campaign, $ids);
            $addedCount += $addedInBatch;
            
            // Commit et clear
            $this->entityManager->flush();
            $this->entityManager->clear();
            
            $offset += self::BATCH_SIZE;
            $batchNumber++;
            
        } while (!empty($ids));
        
        // Mettre à jour le compte
        $campaign->setTotalRecipients($addedCount);
        $this->entityManager->persist($campaign);
        $this->entityManager->flush();
        
        $this->logger->info("=== SÉLECTION FILTRÉE TERMINÉE ===");
        $this->logger->info("Total ajouté: $addedCount abonnés");
        
        return $addedCount;
    }
}