<?php

namespace App\Session;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/**
 * Sessions : fichiers par defaut. Avec SESSION_IN_DB=1, sessions en base (table "sessions"),
 * indispensable des que le site tourne sur plusieurs serveurs (sinon la connexion et le jeton CSRF se perdent d'un serveur a l'autre).
 */
final class SessionHandlerFactory
{
    public static function create(bool $inDb, Connection $connection, string $projectDir = ''): \SessionHandlerInterface
    {
        if (!$inDb) {
            // Dossier PROPRE au site : le dossier systeme (/var/lib/php/sessions) n'est pas lisible par le nettoyage de PHP
            // sur la plupart des hebergements ("ps_files_cleanup_dir ... Permission denied" dans les journaux).
            $dir = '' !== $projectDir ? $projectDir . '/var/sessions' : null;
            if (null !== $dir && !is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }

            return new NativeFileSessionHandler(null !== $dir && is_dir($dir) && is_writable($dir) ? $dir : null);
        }

        return new PdoSessionHandler($connection->getNativeConnection(), ['db_table' => 'sessions', 'lock_mode' => PdoSessionHandler::LOCK_TRANSACTIONAL]);
    }
}
