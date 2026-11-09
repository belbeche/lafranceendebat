<?php

namespace App\Service\Backup;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sauvegarde de la base : fichier .sql.gz dans var/backups (hors dossier public), contrôle d'intégrité,
 * rotation (14 jours, 8 dimanches, 12 premiers du mois) et fichier d'état lu par la page d'administration.
 */
class DatabaseBackup
{
    public function __construct(
        private readonly Connection $db,
        #[Autowire('%kernel.project_dir%/var/backups')] private readonly string $dir,
    ) {
    }

    public function directory(): string
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0700, true);
        }

        return $this->dir;
    }

    /** @return array{ok:bool,file:?string,size:int,seconds:float,error:?string,at:string} */
    public function run(): array
    {
        $t0 = microtime(true);
        $name = 'lfed_' . date('Y-m-d_His') . '.sql.gz';
        $path = $this->directory() . '/' . $name;
        $state = ['ok' => false, 'file' => $name, 'size' => 0, 'seconds' => 0.0, 'error' => null, 'at' => date('c')];
        try {
            $this->dump($path);
            $this->verify($path);
            $state['ok'] = true;
            $state['size'] = (int) filesize($path);
            $this->rotate();
        } catch (\Throwable $e) {
            $state['error'] = mb_substr($e->getMessage(), 0, 300);
            @unlink($path);
            $state['file'] = null;
        }
        $state['seconds'] = round(microtime(true) - $t0, 1);
        $this->writeState($state);

        return $state;
    }

    /** @return list<array{name:string,size:int,time:int}> du plus récent au plus ancien */
    public function files(): array
    {
        $out = [];
        foreach (glob($this->directory() . '/lfed_*.sql.gz') ?: [] as $f) {
            $out[] = ['name' => basename($f), 'size' => (int) filesize($f), 'time' => (int) filemtime($f)];
        }
        usort($out, static fn ($a, $b) => $b['time'] <=> $a['time']);

        return $out;
    }

    public function path(string $name): ?string
    {
        if (!preg_match('/^lfed_\d{4}-\d{2}-\d{2}_\d{6}\.sql\.gz$/', $name)) {
            return null;
        }
        $p = $this->directory() . '/' . $name;

        return is_file($p) ? $p : null;
    }

    public function state(): ?array
    {
        $f = $this->directory() . '/status.json';

        return is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: null) : null;
    }

    /** Problèmes de surveillance : sauvegarde absente ou trop vieille, taille anormale, échec de la dernière. @return list<string> */
    public function problems(): array
    {
        $p = [];
        $files = $this->files();
        $state = $this->state();
        if (!$files) {
            return ['Aucune sauvegarde n\'existe.'];
        }
        if ($state && !($state['ok'] ?? false)) {
            $p[] = 'La dernière tentative a échoué : ' . ($state['error'] ?? 'raison inconnue') . '.';
        }
        if (time() - $files[0]['time'] > 26 * 3600) {
            $p[] = 'La dernière sauvegarde réussie date de plus de 26 heures (' . date('d/m/Y H:i', $files[0]['time']) . ').';
        }
        if (isset($files[1]) && $files[1]['size'] > 0 && $files[0]['size'] < 0.7 * $files[1]['size']) {
            $p[] = sprintf('La taille a chuté de plus de 30 %% (%d Ko contre %d Ko) : suppression anormale possible.', intdiv($files[0]['size'], 1024), intdiv($files[1]['size'], 1024));
        }
        $free = @disk_free_space($this->directory());
        $total = @disk_total_space($this->directory());
        if ($free && $total && $free / $total < 0.1) {
            $p[] = 'Moins de 10 % d\'espace disque libre.';
        }

        return $p;
    }

    private function dump(string $path): void
    {
        $gz = gzopen($path, 'wb9');
        if (!$gz) {
            throw new \RuntimeException('Impossible d\'écrire dans ' . $this->dir);
        }
        $w = static fn (string $s) => gzwrite($gz, $s);
        $platform = $this->db->getDatabasePlatform();
        $isMysql = str_contains(strtolower($platform::class), 'mysql') || str_contains(strtolower($platform::class), 'maria');
        $w("-- La France en Débat : sauvegarde du " . date('c') . "\n" . ($isMysql ? "SET FOREIGN_KEY_CHECKS=0;\n" : "PRAGMA foreign_keys=OFF;\n"));
        $tables = $isMysql ? $this->db->fetchFirstColumn('SHOW TABLES') : $this->db->fetchFirstColumn("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        foreach ($tables as $t) {
            $q = $this->db->quoteIdentifier((string) $t);
            if ($isMysql) {
                $row = $this->db->fetchNumeric('SHOW CREATE TABLE ' . $q);
                $w("DROP TABLE IF EXISTS $q;\n" . ($row[1] ?? '') . ";\n");
            } else {
                $w("DROP TABLE IF EXISTS $q;\n" . $this->db->fetchOne("SELECT sql FROM sqlite_master WHERE name = ?", [$t]) . ";\n");
            }
            $offset = 0;
            while (true) {
                $rows = $this->db->fetchAllAssociative("SELECT * FROM $q LIMIT 500 OFFSET $offset");
                if (!$rows) {
                    break;
                }
                foreach ($rows as $r) {
                    $vals = array_map(fn ($v) => null === $v ? 'NULL' : $this->db->quote((string) $v), array_values($r));
                    $cols = implode(',', array_map(fn ($c) => $this->db->quoteIdentifier((string) $c), array_keys($r)));
                    $w("INSERT INTO $q ($cols) VALUES (" . implode(',', $vals) . ");\n");
                }
                $offset += 500;
            }
        }
        $w($isMysql ? "SET FOREIGN_KEY_CHECKS=1;\n-- FIN\n" : "-- FIN\n");
        gzclose($gz);
        @chmod($path, 0600);
    }

    private function verify(string $path): void
    {
        $gz = gzopen($path, 'rb');
        if (!$gz) {
            throw new \RuntimeException('Fichier illisible après écriture.');
        }
        $tail = '';
        while (!gzeof($gz)) {
            $tail = substr($tail . (string) gzread($gz, 65536), -64);
        }
        gzclose($gz);
        if (!str_contains($tail, '-- FIN')) {
            throw new \RuntimeException('Contrôle d\'intégrité échoué : fin de fichier absente.');
        }
    }

    private function rotate(): void
    {
        $now = time();
        foreach ($this->files() as $f) {
            $age = ($now - $f['time']) / 86400;
            $keep = $age <= 14
                || ('0' === date('w', $f['time']) && $age <= 56)
                || ('01' === date('d', $f['time']) && $age <= 365);
            if (!$keep) {
                @unlink($this->directory() . '/' . $f['name']);
            }
        }
    }

    private function writeState(array $state): void
    {
        @file_put_contents($this->directory() . '/status.json', json_encode($state), LOCK_EX);
    }
}
