<?php
// src/Twig/AppExtension.php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('pad_start', [$this, 'padStart']),
            new TwigFilter('tax_deduction', [$this, 'calculateTaxDeduction']),
            new TwigFilter('real_cost', [$this, 'calculateRealCost']),
        ];
    }
    
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_campaign_status_badge', [$this, 'getCampaignStatusBadge']),
            new TwigFunction('get_subscriber_status_badge', [$this, 'getSubscriberStatusBadge']),
            new TwigFunction('get_user_status_badge', [$this, 'getUserStatusBadge']),
            new TwigFunction('get_donation_status_badge', [$this, 'getDonationStatusBadge']),
            new TwigFunction('association_info', [$this, 'getAssociationInfo']),
            new TwigFilter('ago', [$this, 'timeAgo']),
        ];
    }
    
    public function padStart(string $value, int $length, string $padString = '0'): string
    {
        return str_pad($value, $length, $padString, STR_PAD_LEFT);
    }
    
    public function calculateTaxDeduction(float $amount): float
    {
        return round($amount * 0.66, 2);
    }
    
    public function calculateRealCost(float $amount): float
    {
        return round($amount * 0.34, 2);
    }
    
    /**
     * Informations sur l'éditeur du site (micro-entreprise), lues dans l'environnement.
     * Les variables PUBLISHER_* sont prioritaires ; les anciennes ASSOCIATION_* sont encore lues pour ne pas casser un .env existant.
     */
    public function getAssociationInfo(string $key): string
    {
        $env = static fn (string $new, string $old, string $default): string => (string) ($_ENV[$new] ?? $_ENV[$old] ?? $default);
        $info = [
            'name' => $env('PUBLISHER_NAME', 'ASSOCIATION_NAME', 'La France en Débat'),
            'address' => $env('PUBLISHER_ADDRESS', 'ASSOCIATION_ADDRESS', 'Adresse non renseignée'),
            'siret' => $env('PUBLISHER_SIRET', 'ASSOCIATION_SIRET', 'Non renseigné'),
            'contact' => $env('PUBLISHER_CONTACT', 'ASSOCIATION_PRESIDENT', ''),
        ];

        return $info[$key] ?? '';
    }

    /**
     * Returns the badge class for a campaign status.
     */
    public function getCampaignStatusBadge(string $status): string
    {
        return match($status) {
            'pending' => 'warning',
            'processing' => 'primary',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'secondary',
            'paused' => 'info',
            'scheduled' => 'dark',
            default => 'secondary',
        };
    }

    /**
     * Returns the badge class for a subscriber's status.
     */
    public function getSubscriberStatusBadge(bool $isActive): string
    {
        return $isActive ? 'success' : 'danger';
    }

    /**
     * Returns the badge class for a user's status.
     */
    public function getUserStatusBadge(bool $isEnabled): string
    {
        return $isEnabled ? 'success' : 'danger';
    }

    /**
     * Returns the badge class for a donation's status.
     */
    public function getDonationStatusBadge(string $status): string
    {
        return match($status) {
            'completed' => 'success',
            'pending' => 'warning',
            'failed' => 'danger',
            'refunded' => 'secondary',
            default => 'secondary',
        };
    }

     public function timeAgo(\DateTimeInterface $date): string
    {
        $now = new \DateTime();
        $diff = $now->diff($date);
        
        if ($diff->y > 0) {
            return $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
        }
        if ($diff->m > 0) {
            return $diff->m . ' mois';
        }
        if ($diff->d > 0) {
            return $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
        }
        if ($diff->h > 0) {
            return $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
        }
        if ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
        return 'quelques secondes';
    }
}
