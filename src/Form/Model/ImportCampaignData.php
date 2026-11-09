<?php

// src/Form/Model/ImportCampaignData.php

namespace App\Form\Model;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportCampaignData
{
    private ?UploadedFile $csvFile = null;
    private ?string $delimiter = ',';
    private ?bool $hasHeader = true;
    private ?bool $skipDuplicates = true;
    private ?string $campaignName = null;
    private ?string $campaignSource = null;
    private ?\DateTimeInterface $campaignDate = null;

    // Getters et Setters
    public function getCsvFile(): ?UploadedFile
    {
        return $this->csvFile;
    }

    public function setCsvFile(?UploadedFile $csvFile): self
    {
        $this->csvFile = $csvFile;
        return $this;
    }

    public function getDelimiter(): ?string
    {
        return $this->delimiter;
    }

    public function setDelimiter(?string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }

    public function getHasHeader(): ?bool
    {
        return $this->hasHeader;
    }

    public function setHasHeader(?bool $hasHeader): self
    {
        $this->hasHeader = $hasHeader;
        return $this;
    }

    public function getSkipDuplicates(): ?bool
    {
        return $this->skipDuplicates;
    }

    public function setSkipDuplicates(?bool $skipDuplicates): self
    {
        $this->skipDuplicates = $skipDuplicates;
        return $this;
    }

    public function getCampaignName(): ?string
    {
        return $this->campaignName;
    }

    public function setCampaignName(?string $campaignName): self
    {
        $this->campaignName = $campaignName;
        return $this;
    }

    public function getCampaignSource(): ?string
    {
        return $this->campaignSource;
    }

    public function setCampaignSource(?string $campaignSource): self
    {
        $this->campaignSource = $campaignSource;
        return $this;
    }

    public function getCampaignDate(): ?\DateTimeInterface
    {
        return $this->campaignDate;
    }

    public function setCampaignDate(?\DateTimeInterface $campaignDate): self
    {
        $this->campaignDate = $campaignDate;
        return $this;
    }
}