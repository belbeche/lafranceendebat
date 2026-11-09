<?php
// src/Service/Newsletter/CampaignImportService.php

namespace App\Service\Newsletter;

use App\Entity\NewsletterCampaign;
use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

class CampaignImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NewsletterSubscriberRepository $subscriberRepository,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Importe des emails depuis un fichier pour une campagne spécifique
     * 
     * @return array ['imported' => int, 'invalid' => int, 'existing' => int, 'errors' => array]
     */
    public function importEmailsForCampaign(
        UploadedFile $file, 
        NewsletterCampaign $campaign, 
        array $config
    ): array {
        $result = [
            'imported' => 0,
            'invalid' => 0,
            'existing' => 0,
            'errors' => []
        ];

        try {
            // Lire le fichier
            $emails = $this->parseFile($file, $config);
            
            if (empty($emails)) {
                $result['errors'][] = 'Aucun email valide trouvé dans le fichier';
                return $result;
            }

            // Normaliser et valider les emails
            $validEmails = [];
            foreach ($emails as $email) {
                $email = trim(strtolower($email));
                
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $validEmails[] = $email;
                } else {
                    $result['invalid']++;
                }
            }

            // Supprimer les doublons si demandé
            if ($config['skip_duplicates'] ?? true) {
                $uniqueEmails = array_unique($validEmails);
                $result['existing'] += count($validEmails) - count($uniqueEmails);
                $validEmails = $uniqueEmails;
            }

            // IMPORTANT : On ne crée PAS d'abonnés, on ajoute simplement
            // les emails existants à la campagne
            $existingSubscribers = $this->subscriberRepository->findBy(['email' => $validEmails]);
            $existingEmailsMap = [];
            
            foreach ($existingSubscribers as $subscriber) {
                $email = strtolower($subscriber->getEmail());
                $existingEmailsMap[$email] = $subscriber;
                
                // Ajouter à la campagne
                if (!$campaign->getSubscribers()->contains($subscriber)) {
                    $campaign->addSubscriber($subscriber);
                    $result['imported']++;
                }
            }

            // Pour les emails qui n'existent pas dans la base
            // Soit on les ignore, soit on les crée selon le paramètre
            $nonExistingEmails = array_diff($validEmails, array_keys($existingEmailsMap));
            
            if ($config['create_new_subscribers'] ?? false) {
                // Optionnel : créer de nouveaux abonnés
                foreach ($nonExistingEmails as $email) {
                    try {
                        $subscriber = new NewsletterSubscriber();
                        $subscriber->setEmail($email)
                            ->setIsActive(true)
                            ->setSubscribedAt(new \DateTimeImmutable())
                            ->setUnsubscribeToken(bin2hex(random_bytes(32)));
                        
                        $violations = $this->validator->validate($subscriber);
                        if ($violations->count() === 0) {
                            $this->entityManager->persist($subscriber);
                            $campaign->addSubscriber($subscriber);
                            $result['imported']++;
                        } else {
                            $result['errors'][] = "Email invalide: $email";
                        }
                    } catch (\Exception $e) {
                        $result['errors'][] = "Erreur avec $email: " . $e->getMessage();
                    }
                }
                
                $this->entityManager->flush();
            } else {
                // On ignore les emails qui n'existent pas
                $result['existing'] += count($nonExistingEmails);
            }

        } catch (\Exception $e) {
            $this->logger->error('Erreur import campagne: ' . $e->getMessage());
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Parse le fichier CSV et extrait les emails
     */
    private function parseFile(UploadedFile $file, array $config): array
    {
        $emails = [];
        $delimiter = $config['delimiter'] ?? ',';
        $emailColumn = $config['email_column'] ?? 0;
        $hasHeader = $config['has_header'] ?? true;
        
        if (($handle = fopen($file->getPathname(), 'r')) !== false) {
            $row = 0;
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
                $row++;
                
                if ($hasHeader && $row === 1) {
                    // Trouver l'index de la colonne email si le nom est fourni
                    if (is_string($emailColumn) && $emailColumn !== '') {
                        $header = array_map('trim', $data);
                        $emailIndex = array_search(strtolower($emailColumn), array_map('strtolower', $header));
                        if ($emailIndex !== false) {
                            $emailColumn = $emailIndex;
                        } else {
                            $emailColumn = 0;
                        }
                    }
                    continue;
                }
                
                if (isset($data[$emailColumn])) {
                    $email = trim($data[$emailColumn]);
                    if (!empty($email)) {
                        $emails[] = $email;
                    }
                }
            }
            fclose($handle);
        }
        
        return $emails;
    }
}