<?php
// bin/generate-mercure-tokens.php

require_once __DIR__.'/../vendor/autoload.php';

use Firebase\JWT\JWT;

// Clé secrète (identique pour publisher et subscriber)
$secretKey = 'o1NnTNqrLpfFXbcDjwmjPeLw/I5TpYv+BgxpcbdSBLw';

// Token Publisher (peut publier)
$publisherPayload = [
    'mercure' => [
        'publish' => ['*'],  // Peut publier sur tous les topics
    ],
    'exp' => time() + 3600 // Expire dans 1 heure
];

// Token Subscriber (peut s'abonner)
$subscriberPayload = [
    'mercure' => [
        'subscribe' => ['*'], // Peut s'abonner à tous les topics
    ],
    'exp' => time() + 3600 // Expire dans 1 heure
];

// Génération des tokens
$publisherToken = JWT::encode($publisherPayload, $secretKey, 'HS256');
$subscriberToken = JWT::encode($subscriberPayload, $secretKey, 'HS256');

echo "=== TOKENS MERCURE ===\n";
echo "Publisher Token:\n$publisherToken\n\n";
echo "Subscriber Token:\n$subscriberToken\n\n";
echo "Secret Key: $secretKey\n";