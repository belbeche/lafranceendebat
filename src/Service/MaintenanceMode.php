<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Mode maintenance : un simple fichier drapeau (var/maintenance.json), donc actif même si la base de données est en panne. */
class MaintenanceMode
{
    private ?array $state = null;

    public function __construct(#[Autowire('%kernel.project_dir%/var/maintenance.json')] private readonly string $file)
    {
    }

    public function isOn(): bool
    {
        return null !== $this->read();
    }

    /** @return array{since:int,message:string,until:?int,by:string}|null */
    public function read(): ?array
    {
        // Relu à chaque appel (fichier minuscule) : un changement est vu tout de suite, même par un processus longue durée.
        clearstatcache(true, $this->file);
        $raw = is_file($this->file) ? @file_get_contents($this->file) : false;
        $d = $raw ? json_decode($raw, true) : null;
        $this->state = is_array($d) ? $d : [];
        if (!$this->state) {
            return null;
        }
        // Une maintenance annoncée avec une durée se termine seule : jamais de site bloqué par oubli.
        if (!empty($this->state['until']) && $this->state['until'] < time() - 3600) {
            @unlink($this->file);
            $this->state = [];

            return null;
        }

        return $this->state + ['since' => time(), 'message' => '', 'until' => null, 'by' => ''];
    }

    public function enable(string $message = '', ?int $minutes = null, string $by = 'console'): void
    {
        $dir = \dirname($this->file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $state = ['since' => time(), 'message' => mb_substr(trim($message), 0, 300), 'until' => $minutes ? time() + $minutes * 60 : null, 'by' => mb_substr($by, 0, 80)];
        file_put_contents($this->file, json_encode($state, JSON_UNESCAPED_UNICODE), LOCK_EX);
        $this->state = $state;
    }

    public function disable(): void
    {
        if (is_file($this->file)) {
            @unlink($this->file);
        }
        $this->state = [];
    }
}
