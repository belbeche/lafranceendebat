<?php

namespace App\Service\Ses;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Verifie l'authenticite d'un message Amazon SNS (signature, certificat, rubrique attendue). */
class SnsVerifier
{
    private const SIGNED_FIELDS = [
        'Notification' => ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'],
        'SubscriptionConfirmation' => ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
        'UnsubscribeConfirmation' => ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
    ];

    public function __construct(private HttpClientInterface $http, private string $expectedTopicArn)
    {
    }

    public function isConfigured(): bool
    {
        return $this->expectedTopicArn !== '';
    }

    /** @param array<string,mixed> $m */
    public function verify(array $m): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }
        $type = (string) ($m['Type'] ?? '');
        if (!isset(self::SIGNED_FIELDS[$type]) || !hash_equals($this->expectedTopicArn, (string) ($m['TopicArn'] ?? ''))) {
            return false;
        }
        // Un message plus vieux d'une heure est rejete (anti-rejeu).
        $ts = strtotime((string) ($m['Timestamp'] ?? ''));
        if (!$ts || abs(time() - $ts) > 3600) {
            return false;
        }
        $version = (string) ($m['SignatureVersion'] ?? '');
        $algo = match ($version) { '1' => OPENSSL_ALGO_SHA1, '2' => OPENSSL_ALGO_SHA256, default => null };
        if ($algo === null || empty($m['Signature']) || empty($m['SigningCertURL'])) {
            return false;
        }
        if (!self::isAmazonCertUrl((string) $m['SigningCertURL'])) {
            return false;
        }
        $string = '';
        foreach (self::SIGNED_FIELDS[$type] as $field) {
            if (isset($m[$field])) {
                $string .= $field . "\n" . $m[$field] . "\n";
            }
        }
        $pem = $this->fetchCertificate((string) $m['SigningCertURL']);
        if (!$pem) {
            return false;
        }
        $key = openssl_pkey_get_public($pem);
        $sig = base64_decode((string) $m['Signature'], true);
        if (!$key || $sig === false) {
            return false;
        }

        return openssl_verify($string, $sig, $key, $algo) === 1;
    }

    public static function isAmazonCertUrl(string $url): bool
    {
        $p = parse_url($url);

        return $p && ($p['scheme'] ?? '') === 'https' && !isset($p['port']) && !isset($p['user'])
            && preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?$/', (string) ($p['host'] ?? '')) === 1
            && str_ends_with((string) ($p['path'] ?? ''), '.pem');
    }

    public static function isAmazonSubscribeUrl(string $url): bool
    {
        $p = parse_url($url);

        return $p && ($p['scheme'] ?? '') === 'https' && !isset($p['port']) && !isset($p['user'])
            && preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?$/', (string) ($p['host'] ?? '')) === 1;
    }

    protected function fetchCertificate(string $url): ?string
    {
        try {
            $r = $this->http->request('GET', $url, ['timeout' => 5, 'max_redirects' => 0]);

            return $r->getStatusCode() === 200 ? $r->getContent() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function confirmSubscription(string $subscribeUrl): void
    {
        if (self::isAmazonSubscribeUrl($subscribeUrl)) {
            try {
                $this->http->request('GET', $subscribeUrl, ['timeout' => 5, 'max_redirects' => 0])->getStatusCode();
            } catch (\Throwable) {
            }
        }
    }
}
