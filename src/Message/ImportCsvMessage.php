<?php

namespace App\Message;

/**
 * Message asynchrone (Messenger) « ImportCsvMessage ».
 *
 * Traité en tâche de fond : un échec ne doit jamais bloquer la requête de l'utilisateur.
 */
class ImportCsvMessage
{
    private array $chunk;
    private int $chunkIndex;
    private int $importLogId;
    private \DateTimeImmutable $createdAt;

    public function __construct(array $chunk, int $chunkIndex, int $importLogId)
    {
        $this->chunk = $chunk;
        $this->chunkIndex = $chunkIndex;
        $this->importLogId = $importLogId;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getChunk(): array
    {
        return $this->chunk;
    }

    public function getChunkIndex(): int
    {
        return $this->chunkIndex;
    }

    public function getImportLogId(): int
    {
        return $this->importLogId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getChunkSize(): int
    {
        return count($this->chunk);
    }
}