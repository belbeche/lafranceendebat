<?php
// src/Service/EmailValidator.php

namespace App\Service;

/**
 * Service métier « EmailValidator ».
 */
class EmailValidator
{
    public function isValidEmail(string $email): bool
    {
        $email = trim($email);
        
        // Validation basique
        if (empty($email)) {
            return false;
        }
        
        // Validation avec filter_var
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Validation de la structure
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }
        
        [$local, $domain] = $parts;
        
        // Vérifier que le domaine a un point
        if (strpos($domain, '.') === false) {
            return false;
        }
        
        // Vérifier la longueur
        if (strlen($local) > 64 || strlen($domain) > 255) {
            return false;
        }
        
        // Liste de domaines invalides
        $invalidDomains = [
            'example.com',
            'test.com',
            'invalid.com',
            'localhost',
            'local',
        ];
        
        if (in_array(strtolower($domain), $invalidDomains)) {
            return false;
        }
        
        return true;
    }
    
    public function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
    
    public function validateEmailList(array $emails): array
    {
        $valid = [];
        $invalid = [];
        
        foreach ($emails as $email) {
            if ($this->isValidEmail($email)) {
                $normalized = $this->normalizeEmail($email);
                $valid[] = $normalized;
            } else {
                $invalid[] = $email;
            }
        }
        
        // Supprimer les doublons
        $valid = array_unique($valid);
        
        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'valid_count' => count($valid),
            'invalid_count' => count($invalid),
        ];
    }
}