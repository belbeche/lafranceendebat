<?php

namespace App\Command;

use App\Entity\ImportLog;
use App\Message\ImportCsvMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use League\Csv\Reader;
use Doctrine\DBAL\Exception\ConnectionException;

#[AsCommand(
    name: 'newsletter:import-csv',
    description: 'Importe des abonnés newsletter depuis un CSV de manière asynchrone',
)]
/**
 * Commande console « NewsletterImportCsvCommand ».
 *
 * Lancer : php bin/console newsletter:import-csv. Importe des abonnés newsletter depuis un CSV de manière asynchrone
 */
class NewsletterImportCsvCommand extends Command
{
    private MessageBusInterface $messageBus;
    private EntityManagerInterface $entityManager;
    private string $projectDir;

    public function __construct(
        MessageBusInterface $messageBus,
        EntityManagerInterface $entityManager,
        string $projectDir
    ) {
        parent::__construct();
        $this->messageBus = $messageBus;
        $this->entityManager = $entityManager;
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Chemin du fichier CSV')
            ->addOption('import-name', null, InputOption::VALUE_OPTIONAL, 'Nom de l\'import', 'Import CSV')
            ->addOption('source', null, InputOption::VALUE_OPTIONAL, 'Source des données', 'csv_import')
            ->addOption('chunk', 'c', InputOption::VALUE_OPTIONAL, 'Nombre de lignes par chunk', 500)
            ->addOption('delimiter', 'd', InputOption::VALUE_OPTIONAL, 'Délimiteur CSV', ',')
            ->addOption('unique-field', 'u', InputOption::VALUE_OPTIONAL, 'Champ pour détecter les doublons', 'email')
            ->addOption('skip-duplicates', null, InputOption::VALUE_NONE, 'Ignorer les abonnés déjà existants')
            ->addOption('update-duplicates', null, InputOption::VALUE_NONE, 'Mettre à jour les abonnés existants')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler l\'import sans écrire en base')
            ->addOption('skip-header-validation', null, InputOption::VALUE_NONE, 'Ne pas valider les en-têtes')
            ->addOption('default-locale', null, InputOption::VALUE_OPTIONAL, 'Locale par défaut', 'fr')
            ->addOption('default-status', null, InputOption::VALUE_OPTIONAL, 'Statut par défaut', 'active')
            ->addOption('tags', null, InputOption::VALUE_OPTIONAL, 'Tags à ajouter aux abonnés (séparés par virgule)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $filePath = $this->resolveFilePath($input->getArgument('file'));
        $importName = $input->getOption('import-name');
        $source = $input->getOption('source');
        $chunkSize = (int) $input->getOption('chunk');
        $delimiter = $input->getOption('delimiter');
        $uniqueField = $input->getOption('unique-field');
        $skipDuplicates = $input->getOption('skip-duplicates');
        $updateDuplicates = $input->getOption('update-duplicates');
        $dryRun = $input->getOption('dry-run');
        $skipHeaderValidation = $input->getOption('skip-header-validation');
        $defaultLocale = $input->getOption('default-locale');
        $defaultStatus = $input->getOption('default-status');
        $tags = $input->getOption('tags') ? explode(',', $input->getOption('tags')) : [];

        // Vérification du fichier
        if (!$filePath || !file_exists($filePath)) {
            $io->error("Le fichier '{$input->getArgument('file')}' n'existe pas.");
            $io->note("Essayez avec un chemin complet ou placez le fichier dans: {$this->projectDir}/");
            return Command::FAILURE;
        }

        if ($skipDuplicates && $updateDuplicates) {
            $io->error("Les options --skip-duplicates et --update-duplicates sont mutuellement exclusives.");
            return Command::FAILURE;
        }

        // Vérifier la connexion à la base de données avant de commencer
        try {
            $this->entityManager->getConnection()->connect();
        } catch (ConnectionException $e) {
            $io->error("Impossible de se connecter à la base de données: " . $e->getMessage());
            return Command::FAILURE;
        }

        $importLog = null;
        
        try {
            // Créer le log d'import
            $importLog = new ImportLog();
            $importLog->setFileName(basename($filePath));
            $importLog->setImportName($importName);
            $importLog->setSource($source);
            $importLog->setStatus('processing');
            $importLog->setStartedAt(new \DateTimeImmutable());
            $importLog->setTotalRecords(0);
            $importLog->setProcessedRows(0);
            $importLog->setSkippedRows(0);
            if ($importLog) {
                $importLog->setFailedRecords($importLog->getFailedRecords() + 1);
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
            
            if (!$dryRun) {
                $this->entityManager->persist($importLog);
                $this->entityManager->flush();
                $this->entityManager->clear(); // Clear après flush
            }

            // Lecture du CSV
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setDelimiter($delimiter);
            $csv->setHeaderOffset(0);
            
            $totalRecords = $csv->count();
            $headers = $csv->getHeader();
            
            // Valider les headers
            $requiredFields = ['email'];
            $optionalFields = ['display_name', 'locale', 'first_name', 'last_name', 'phone', 'campaign_name', 'source', 'notes', 'status'];
            
            if (!$skipHeaderValidation) {
                foreach ($requiredFields as $field) {
                    if (!in_array($field, $headers) && !in_array(str_replace('_', ' ', $field), $headers)) {
                        $io->error("Champ requis manquant: '$field'");
                        $io->note("Champs disponibles: " . implode(', ', $headers));
                        $io->note("Utilisez --skip-header-validation pour ignorer cette vérification");
                        return Command::FAILURE;
                    }
                }
            }
            
            // Mapping des noms de colonnes
            $headerMapping = $this->mapHeaders($headers);
            
            $io->title("📥 Import Newsletter - $importName");
            $io->info([
                "Fichier: " . basename($filePath),
                "Total lignes: $totalRecords",
                "Chunk size: $chunkSize",
                "Champ unique: $uniqueField",
                "Mode doublons: " . $this->getDuplicatesMode($skipDuplicates, $updateDuplicates),
                "Source: $source",
                "Locale par défaut: $defaultLocale",
                "Statut par défaut: $defaultStatus",
                "Dry run: " . ($dryRun ? '✅ OUI' : '❌ NON')
            ]);

            if ($tags) {
                $io->note("Tags à appliquer: " . implode(', ', $tags));
            }

            // Mettre à jour le log avec le total
            if (!$dryRun) {
                $importLog->setTotalRecords($totalRecords);
                $this->entityManager->flush();
                $this->entityManager->clear(); // Clear après flush
            }

            // Dry run mode
            if ($dryRun) {
                return $this->dryRunAnalysis($csv, $uniqueField, $headers, $io);
            }

            // Si EntityManager est fermé, on le recrée
            if (!$this->entityManager->isOpen()) {
                $this->entityManager->getConnection()->connect();
                // Recharger l'importLog
                $importLog = $this->entityManager->find(ImportLog::class, $importLog->getId());
            }

            // Tableau pour suivre les doublons dans le fichier
            $seenInFile = [];
            $duplicatesInFile = 0;
            $chunkCount = 0;
            $currentChunk = [];
            $processedRows = 0;
            $skippedRows = 0;

            foreach ($csv as $index => $record) {
                // Vérifier périodiquement que l'EntityManager est ouvert
                if ($index % 100 === 0 && !$this->entityManager->isOpen()) {
                    $this->entityManager->getConnection()->connect();
                    // Recharger l'importLog
                    if ($importLog && $importLog->getId()) {
                        $importLog = $this->entityManager->find(ImportLog::class, $importLog->getId());
                    }
                }
                
                // Normaliser les noms de colonnes
                $normalizedRecord = $this->normalizeRecord($record, $headerMapping);
                
                $uniqueValue = $normalizedRecord[$uniqueField] ?? null;
                
                // Validation email
                if (empty($uniqueValue) || !filter_var($uniqueValue, FILTER_VALIDATE_EMAIL)) {
                    $io->warning("Ligne " . ($index + 2) . ": Email invalide ou manquant - '" . ($uniqueValue ?? 'vide') . "'");
                    $skippedRows++;
                    if ($importLog) {
                        $importLog->setFailedRows($importLog->getFailedRows() + 1);
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                    }
                    continue;
                }
                
                // Vérifier les doublons dans le fichier lui-même
                if ($skipDuplicates && isset($seenInFile[$uniqueValue])) {
                    $duplicatesInFile++;
                    $skippedRows++;
                    $io->text("⚠️  Doublon ignoré (ligne " . ($index + 2) . "): $uniqueField = '$uniqueValue'");
                    if ($importLog) {
                        $importLog->setSkippedRows($importLog->getSkippedRows() + 1);
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                    }
                    continue;
                }

                $seenInFile[$uniqueValue] = true;

                // Ajouter metadata et valeurs par défaut
                $normalizedRecord['_metadata'] = [
                    'csv_line' => $index + 2,
                    'unique_field' => $uniqueField,
                    'unique_value' => $uniqueValue,
                    'skip_duplicates' => $skipDuplicates,
                    'update_duplicates' => $updateDuplicates,
                    'import_log_id' => $importLog ? $importLog->getId() : null,
                    'default_locale' => $defaultLocale,
                    'default_status' => $defaultStatus,
                    'tags' => $tags,
                    'source' => $source,
                    'import_name' => $importName
                ];

                // Appliquer les valeurs par défaut
                if (empty($normalizedRecord['locale'])) {
                    $normalizedRecord['locale'] = $defaultLocale;
                }
                if (empty($normalizedRecord['status'])) {
                    $normalizedRecord['status'] = $defaultStatus;
                }

                $currentChunk[] = $normalizedRecord;
                $processedRows++;
                
                if ($importLog) {
                    $importLog->setProcessedRows($importLog->getProcessedRows() + 1);
                    // Flush périodiquement mais pas trop souvent
                    if ($processedRows % 50 === 0) {
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                        // Recharger l'importLog après clear
                        if ($importLog->getId()) {
                            $importLog = $this->entityManager->find(ImportLog::class, $importLog->getId());
                        }
                    }
                }

                // Envoyer le chunk quand il est plein
                if (count($currentChunk) >= $chunkSize) {
                    $this->dispatchChunk($currentChunk, $chunkCount, $importLog, $io);
                    $currentChunk = [];
                    $chunkCount++;
                }
            }

            // Envoyer le dernier chunk
            if (!empty($currentChunk)) {
                $this->dispatchChunk($currentChunk, $chunkCount, $importLog, $io);
                $chunkCount++;
            }

            // Finaliser le log
            if ($importLog) {
                $importLog->setStatus('queued');
                $importLog->setCompletedAt(new \DateTimeImmutable());
                $this->entityManager->flush();
                $this->entityManager->clear();
            }

            // Résumé
            $io->success([
                "✅ Import newsletter programmé avec succès !",
                "📊 Statistiques:",
                "   - Fichier: " . basename($filePath),
                "   - Lignes totales: $totalRecords",
                "   - Lignes valides: $processedRows",
                "   - Lignes ignorées: $skippedRows",
                "   - Doublons dans fichier: $duplicatesInFile",
                "   - Messages créés: $chunkCount",
                "   - ID d'import: " . ($importLog ? "#" . $importLog->getId() : "N/A"),
                "",
                "🚀 Les messages sont dans la file Messenger",
                "👉 Exécutez 'php bin/console messenger:consume async' pour traiter"
            ]);

            if ($importLog) {
                $io->note("Suivez la progression: php bin/console import:status " . $importLog->getId());
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            // Gestion robuste de l'EntityManager fermé
            try {
                if ($importLog) {
                    // Essayer de recréer la connexion de l'EntityManager
                    if (!$this->entityManager->isOpen()) {
                        $this->entityManager->getConnection()->connect();
                    }
                    
                    $importLog->setStatus('failed');
                    $importLog->setErrorMessage(substr($e->getMessage(), 0, 255));
                    $this->entityManager->flush();
                }
            } catch (\Exception $flushException) {
                // Si on ne peut pas flush, on log seulement
                $io->warning("Impossible de sauvegarder l'erreur dans le log: " . $flushException->getMessage());
            }
            
            $io->error([
                "❌ Erreur lors de l'import:",
                $e->getMessage(),
                "Fichier: " . ($filePath ?? 'N/A'),
                "Ligne: " . $e->getLine(),
                "Trace: " . substr($e->getTraceAsString(), 0, 500) . "..."
            ]);
            
            return Command::FAILURE;
        }
    }

    private function resolveFilePath(string $filePath): ?string
    {
        // Chemin absolu
        if (file_exists($filePath)) {
            return realpath($filePath);
        }
        
        // Chemin relatif au projet
        $projectPath = $this->projectDir . '/' . ltrim($filePath, '/');
        if (file_exists($projectPath)) {
            return realpath($projectPath);
        }
        
        // Chemin relatif au répertoire courant
        $currentDirPath = getcwd() . '/' . ltrim($filePath, '/');
        if (file_exists($currentDirPath)) {
            return realpath($currentDirPath);
        }
        
        return null;
    }

    private function mapHeaders(array $headers): array
    {
        $mapping = [];
        $commonVariations = [
            'email' => ['email', 'e-mail', 'mail', 'courriel'],
            'display_name' => ['display_name', 'display name', 'name', 'nom'],
            'first_name' => ['first_name', 'first name', 'prenom', 'prénom'],
            'last_name' => ['last_name', 'last name', 'nom', 'surname'],
            'locale' => ['locale', 'langue', 'language', 'lang'],
            'phone' => ['phone', 'telephone', 'téléphone', 'tel'],
            'campaign_name' => ['campaign_name', 'campaign name', 'campaign', 'campagne'],
            'source' => ['source', 'origine', 'origin'],
            'notes' => ['notes', 'note', 'commentaires', 'comments'],
            'status' => ['status', 'statut', 'etat', 'state']
        ];
        
        foreach ($headers as $header) {
            $normalized = strtolower(trim($header));
            foreach ($commonVariations as $standard => $variations) {
                if (in_array($normalized, $variations)) {
                    $mapping[$header] = $standard;
                    break;
                }
            }
            if (!isset($mapping[$header])) {
                $mapping[$header] = $normalized;
            }
        }
        
        return $mapping;
    }

    private function normalizeRecord(array $record, array $headerMapping): array
    {
        $normalized = [];
        foreach ($record as $key => $value) {
            $standardKey = $headerMapping[$key] ?? $key;
            $normalized[$standardKey] = trim($value);
        }
        return $normalized;
    }

    private function getDuplicatesMode(bool $skipDuplicates, bool $updateDuplicates): string
    {
        if ($skipDuplicates) return 'Ignorer les doublons';
        if ($updateDuplicates) return 'Mettre à jour les doublons';
        return 'Accepter tous (risque de doublons)';
    }

    private function dryRunAnalysis(Reader $csv, string $uniqueField, array $headers, SymfonyStyle $io): int
    {
        $io->title('🐣 DRY RUN - Analyse du fichier');
        
        $stats = [
            'total' => 0,
            'valid_emails' => 0,
            'invalid_emails' => 0,
            'empty_emails' => 0,
            'duplicates' => 0
        ];
        
        $seenEmails = [];
        $invalidEmails = [];
        $duplicateEmails = [];
        
        foreach ($csv as $index => $record) {
            $stats['total']++;
            $email = $record[$uniqueField] ?? $record['email'] ?? '';
            
            if (empty($email)) {
                $stats['empty_emails']++;
                $invalidEmails[] = ['line' => $index + 2, 'email' => '(vide)', 'reason' => 'Email vide'];
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stats['invalid_emails']++;
                $invalidEmails[] = ['line' => $index + 2, 'email' => $email, 'reason' => 'Format invalide'];
            } else {
                if (isset($seenEmails[$email])) {
                    $stats['duplicates']++;
                    $duplicateEmails[] = ['line' => $index + 2, 'email' => $email, 'first_line' => $seenEmails[$email]];
                } else {
                    $stats['valid_emails']++;
                    $seenEmails[$email] = $index + 2;
                }
            }
        }
        
        // Affichage des statistiques
        $io->table(
            ['Statistique', 'Valeur', '%'],
            [
                ['Lignes totales', $stats['total'], '100%'],
                ['Emails valides', $stats['valid_emails'], round($stats['valid_emails']/$stats['total']*100, 1) . '%'],
                ['Emails invalides', $stats['invalid_emails'], round($stats['invalid_emails']/$stats['total']*100, 1) . '%'],
                ['Emails vides', $stats['empty_emails'], round($stats['empty_emails']/$stats['total']*100, 1) . '%'],
                ['Doublons détectés', $stats['duplicates'], round($stats['duplicates']/$stats['total']*100, 1) . '%'],
                ['Abonnés uniques', $stats['valid_emails'] - $stats['duplicates'], round(($stats['valid_emails'] - $stats['duplicates'])/$stats['total']*100, 1) . '%']
            ]
        );
        
        // Afficher quelques exemples d'erreurs
        if (!empty($invalidEmails)) {
            $io->section('📝 Exemples d\'emails invalides');
            $sample = array_slice($invalidEmails, 0, 5);
            $rows = [];
            foreach ($sample as $error) {
                $rows[] = [$error['line'], $error['email'], $error['reason']];
            }
            $io->table(['Ligne', 'Email', 'Raison'], $rows);
        }
        
        if (!empty($duplicateEmails)) {
            $io->section('📝 Exemples de doublons détectés');
            $sample = array_slice($duplicateEmails, 0, 5);
            $rows = [];
            foreach ($sample as $dup) {
                $rows[] = [$dup['line'], $dup['email'], $dup['first_line']];
            }
            $io->table(['Ligne', 'Email', 'Première occurrence'], $rows);
        }
        
        $io->success('✅ Dry run terminé. Aucune donnée n\'a été importée.');
        return Command::SUCCESS;
    }

    private function dispatchChunk(array $chunk, int $chunkIndex, ?ImportLog $importLog, SymfonyStyle $io): void
    {
        $importLogId = $importLog ? $importLog->getId() : null;
        $message = new ImportCsvMessage($chunk, $chunkIndex, $importLogId);
        $this->messageBus->dispatch($message);
        
        $io->text("📤 Chunk #$chunkIndex: " . count($chunk) . " abonnés" . ($importLog ? " -> Import #" . $importLog->getId() : ""));
    }
}