<?php

namespace App\Service\Security;

/** Refuse les adresses e-mail jetables ou suspectes. Liste dans config/disposable_domains.txt (modifiable sans toucher au code). */
class DisposableEmailPolicy
{
    /** @var array<string,true>|null */
    private ?array $blocked = null;
    /** @var array<string,true> */
    private array $allowed = [];

    public function __construct(private readonly string $listFile, private readonly string $kernelEnvironment = 'prod')
    {
    }

    /** Le domaine recoit-il du courrier ? (enregistrement MX, ou A a defaut). Echec reseau = on laisse passer. Desactive hors production. */
    public function hasMailServer(string $email): bool
    {
        $d = $this->domainOf($email);
        if ('prod' !== $this->kernelEnvironment || '' === $d || !function_exists('checkdnsrr')) {
            return true;
        }
        $d = rtrim($d, '.') . '.';
        $mx = @checkdnsrr($d, 'MX');
        if ($mx || @checkdnsrr($d, 'A') || @checkdnsrr($d, 'AAAA')) {
            return true;
        }

        // Aucun enregistrement : on ne refuse que si la resolution DNS fonctionne (un domaine connu repond).
        return !@checkdnsrr('gmail.com.', 'MX');
    }

    public function domainOf(string $email): string
    {
        $at = strrpos($email, '@');

        return false === $at ? '' : mb_strtolower(trim(substr($email, $at + 1)));
    }

    public function isSuspicious(string $email): bool
    {
        $this->load();
        $domain = $this->domainOf($email);
        if ('' === $domain) {
            return false;
        }
        // Le domaine et tous ses parents : a.b.mailinator.com => b.mailinator.com => mailinator.com
        $parts = explode('.', $domain);
        for ($i = 0; $i < count($parts) - 1; ++$i) {
            $d = implode('.', array_slice($parts, $i));
            if (isset($this->allowed[$d])) {
                return false;
            }
            if (isset($this->blocked[$d])) {
                return true;
            }
        }

        return false;
    }

    /** "d***@bankwen.com" : suffisant pour un journal sans conserver l'adresse complète d'une personne refusée. */
    public static function mask(string $email): string
    {
        $at = strrpos($email, '@');
        if (false === $at) {
            return mb_substr($email, 0, 3) . '***';
        }

        return mb_substr($email, 0, 1) . '***' . mb_substr($email, $at);
    }

    private function load(): void
    {
        if (null !== $this->blocked) {
            return;
        }
        $this->blocked = [];
        foreach (@file($this->listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = mb_strtolower(trim($line));
            if ('' === $line || '#' === $line[0]) {
                continue;
            }
            if ('!' === $line[0]) {
                $this->allowed[ltrim(substr($line, 1))] = true;
            } else {
                $this->blocked[$line] = true;
            }
        }
    }
}
