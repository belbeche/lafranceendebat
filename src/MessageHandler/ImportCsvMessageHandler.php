<?php

namespace App\MessageHandler;

use App\Entity\ImportLog;
use App\Entity\NewsletterSubscriber;
use App\Message\ImportCsvMessage;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Gestionnaire de message Messenger "ImportCsvMessageHandler".
 *
 * Traité en tâche de fond : un échec ne doit jamais bloquer la requête de l'utilisateur.
 */
#[AsMessageHandler]
class ImportCsvMessageHandler
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private NewsletterSubscriberRepository $subscriberRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        NewsletterSubscriberRepository $subscriberRepository
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->subscriberRepository = $subscriberRepository;
    }

    public function __invoke(ImportCsvMessage $message): void
    {
        $startTime = microtime(true);
        $chunkIndex = $message->getChunkIndex();
        $chunk = $message->getChunk();
        $importLogId = $message->getImportLogId();
        
        // Récupérer le log d'import
        $importLog = $this->entityManager->find(ImportLog::class, $importLogId);
        if (!$importLog) {
            $this->logger->error('ImportLog non trouvé', ['import_log_id' => $importLogId]);
            return;
        }
        
        $this->logger->info('Début traitement chunk newsletter', [
            'chunk_index' => $chunkIndex,
            'chunk_size' => count($chunk),
            'import_id' => $importLogId
        ]);

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        ];

        try {
            // Récupérer les métadonnées
            $metadata = $chunk[0]['_metadata'] ?? [];
            $uniqueField = $metadata['unique_field'] ?? 'email';
            $skipDuplicates = $metadata['skip_duplicates'] ?? false;
            $updateDuplicates = $metadata['update_duplicates'] ?? false;
            $defaultLocale = $metadata['default_locale'] ?? 'fr';
            $defaultStatus = $metadata['default_status'] ?? 'active';
            $tags = $metadata['tags'] ?? [];
            $source = $metadata['source'] ?? 'csv_import';
            
            // Récupérer tous les emails existants en une seule requête
            $emails = array_filter(array_column($chunk, 'email'));
            $existingSubscribers = [];
            
            if (!empty($emails) && ($skipDuplicates || $updateDuplicates)) {
                $existingSubscribers = $this->subscriberRepository->findBy(['email' => array_unique($emails)]);
            }
            
            // Créer un index pour recherche rapide
            $existingIndex = [];
            foreach ($existingSubscribers as $subscriber) {
                $existingIndex[$subscriber->getEmail()] = $subscriber;
            }

            $batchSize = 50;
            $batchCount = 0;

            foreach ($chunk as $row) {
                $rowMetadata = $row['_metadata'] ?? [];
                unset($row['_metadata']);
                
                $email = $row['email'] ?? null;
                
                try {
                    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $stats['errors']++;
                        $importLog->incrementErrorRows();
                        continue;
                    }
                    
                    $existingSubscriber = $existingIndex[$email] ?? null;
                    
                    if ($existingSubscriber) {
                        // Doublon trouvé
                        if ($skipDuplicates) {
                            $stats['skipped']++;
                            $importLog->incrementSkippedRows();
                            continue;
                        }
                        
                        if ($updateDuplicates) {
                            // Mettre à jour l'abonné existant
                            $this->updateSubscriber($existingSubscriber, $row, $rowMetadata);
                            $stats['updated']++;
                            $importLog->incrementProcessedRows();
                        } else {
                            // Créer un nouveau (doublon accepté)
                            $this->createSubscriber($row, $rowMetadata, $importLog);
                            $stats['created']++;
                            $importLog->incrementProcessedRows();
                        }
                    } else {
                        // Nouvel abonné
                        $this->createSubscriber($row, $rowMetadata, $importLog);
                        $stats['created']++;
                        $importLog->incrementProcessedRows();
                    }
                    
                    $batchCount++;
                    
                    // Flush par batch pour optimiser les performances
                    if ($batchCount % $batchSize === 0) {
                        $this->entityManager->flush();
                        $this->entityManager->clear(NewsletterSubscriber::class);
                        
                        // Recharger l'importLog après clear
                        if ($this->entityManager->isOpen()) {
                            $importLog = $this->entityManager->find(ImportLog::class, $importLogId);
                        }
                    }
                    
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $importLog->incrementErrorRows();
                    
                    $this->logger->error('Erreur traitement ligne newsletter', [
                        'chunk_index' => $chunkIndex,
                        'email' => $email,
                        'error' => $e->getMessage(),
                        'row' => json_encode($row)
                    ]);
                }
            }

            // Dernier flush
            $this->entityManager->flush();
            $this->entityManager->clear();

            $executionTime = round(microtime(true) - $startTime, 2);

            // Mettre à jour le log
            $importLog = $this->entityManager->find(ImportLog::class, $importLogId);
            if ($importLog) {
                $importLog->setStatus('completed');
                $this->entityManager->flush();
            }

            $this->logger->info('Chunk newsletter traité', [
                'chunk_index' => $chunkIndex,
                'stats' => $stats,
                'import_id' => $importLogId,
                'execution_time' => $executionTime . 's',
                'memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB'
            ]);

        } catch (\Exception $e) {
            $importLog->setStatus('failed');
            $importLog->setErrorMessage($e->getMessage());
            $this->entityManager->flush();
            
            $this->logger->critical('Échec traitement chunk newsletter', [
                'chunk_index' => $chunkIndex,
                'import_id' => $importLogId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    private function createSubscriber(array $data, array $metadata, ImportLog $importLog): NewsletterSubscriber
    {
        $subscriber = new NewsletterSubscriber();
        
        // Champs obligatoires
        $subscriber->setEmail($data['email']);
        $subscriber->setSubscribedAt(new \DateTimeImmutable());
        
        // Champs optionnels
        if (isset($data['display_name'])) {
            $subscriber->setDisplayName($data['display_name']);
        }
        
        if (isset($data['first_name'])) {
            $subscriber->setFirstName($data['first_name']);
        }
        
        if (isset($data['last_name'])) {
            $subscriber->setLastName($data['last_name']);
        }
        
        if (isset($data['locale'])) {
            $subscriber->setLocale($data['locale']);
        } elseif (isset($metadata['default_locale'])) {
            $subscriber->setLocale($metadata['default_locale']);
        }
        
        if (isset($data['phone'])) {
            $subscriber->setPhone($data['phone']);
        }
        
        if (isset($data['campaign_name'])) {
            $subscriber->setCampaignName($data['campaign_name']);
        }
        
        if (isset($data['source'])) {
            $subscriber->setSource($data['source']);
        } elseif (isset($metadata['source'])) {
            $subscriber->setSource($metadata['source']);
        }
        
        if (isset($data['notes'])) {
            $subscriber->setNotes($data['notes']);
        }
        
        if (isset($data['status'])) {
            $subscriber->setStatus($data['status']);
        } elseif (isset($metadata['default_status'])) {
            $subscriber->setStatus($metadata['default_status']);
        }
        
        // Tags
        if (isset($metadata['tags']) && !empty($metadata['tags'])) {
            $subscriber->setTags($metadata['tags']);
        }
        
        // Lien avec l'import
        $subscriber->setImport($importLog);
        $subscriber->setImportLog($importLog);
        
        $this->entityManager->persist($subscriber);
        
        return $subscriber;
    }

    private function updateSubscriber(NewsletterSubscriber $subscriber, array $data, array $metadata): void
    {
        // Mettre à jour les champs (sauf email qui est l'identifiant)
        if (isset($data['display_name']) && $data['display_name'] !== $subscriber->getDisplayName()) {
            $subscriber->setDisplayName($data['display_name']);
        }
        
        if (isset($data['first_name']) && $data['first_name'] !== $subscriber->getFirstName()) {
            $subscriber->setFirstName($data['first_name']);
        }
        
        if (isset($data['last_name']) && $data['last_name'] !== $subscriber->getLastName()) {
            $subscriber->setLastName($data['last_name']);
        }
        
        if (isset($data['locale']) && $data['locale'] !== $subscriber->getLocale()) {
            $subscriber->setLocale($data['locale']);
        }
        
        if (isset($data['phone']) && $data['phone'] !== $subscriber->getPhone()) {
            $subscriber->setPhone($data['phone']);
        }
        
        if (isset($data['campaign_name']) && $data['campaign_name'] !== $subscriber->getCampaignName()) {
            $subscriber->setCampaignName($data['campaign_name']);
        }
        
        if (isset($data['source']) && $data['source'] !== $subscriber->getSource()) {
            $subscriber->setSource($data['source']);
        }
        
        if (isset($data['notes']) && $data['notes'] !== $subscriber->getNotes()) {
            $subscriber->setNotes($data['notes']);
        }
        
        if (isset($data['status']) && $data['status'] !== $subscriber->getStatus()) {
            $subscriber->setStatus($data['status']);
        }
        
        // Mettre à jour les tags
        if (isset($metadata['tags']) && !empty($metadata['tags'])) {
            $currentTags = $subscriber->getTags() ?? [];
            $newTags = array_unique(array_merge($currentTags, $metadata['tags']));
            $subscriber->setTags($newTags);
        }
        
        // Marquer comme mis à jour
        $subscriber->setUpdatedAt(new \DateTimeImmutable());
    }
}