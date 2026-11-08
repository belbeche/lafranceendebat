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
    public static function create(bool $inDb, Connection $connection): \SessionHandlerInterface
    {
        if (!$inDb) {
            return new NativeFileSessionHandler();
        }

        return new PdoSessionHandler($connection->getNativeConnection(), ['db_table' => 'sessions', 'lock_mode' => PdoSessionHandler::LOCK_TRANSACTIONAL]);
    }
}
