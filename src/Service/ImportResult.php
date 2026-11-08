<?php
// src/Service/ImportResult.php

namespace App\Service;

/**
 * Service métier « ImportResult ».
 */
class ImportResult
{
    private int $importId;
    private int $importedCount;
    private int $failedCount;
    private int $skippedCount;

    public function __construct(int $importId, int $importedCount, int $failedCount, int $skippedCount)
    {
        $this->importId = $importId;
        $this->importedCount = $importedCount;
        $this->failedCount = $failedCount;
        $this->skippedCount = $skippedCount;
    }

    public function getImportId(): int
    {
        return $this->importId;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getTotal(): int
    {
        return $this->importedCount + $this->failedCount + $this->skippedCount;
    }

    public function getSuccessRate(): float
    {
        $total = $this->getTotal();
        if ($total === 0) {
            return 0.0;
        }
        return ($this->importedCount / $total) * 100;
    }
}