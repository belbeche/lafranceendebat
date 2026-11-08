<?php

// src/Form/DataTransformer/FileDataTransformer.php
namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Formulaire Symfony « FileDataTransformer ».
 */
class FileDataTransformer implements DataTransformerInterface
{
    public function transform($value): mixed
    {
        return $value;
    }

    public function reverseTransform($value): mixed
    {
        if ($value instanceof UploadedFile) {
            return $value;
        }
        return null;
    }
}