<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Connexion PDO MySQL (singleton paresseux).
 */
final class Database
{
    private static ?PDO $pdo = null;

    /**
     * Retourne la connexion PDO (créée à la première demande).
     *
     * @throws PDOException si la connexion échoue
     */
    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get('database');
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $c['host'], $c['port'], $c['name'], $c['charset']
            );
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => sprintf(
                    "SET NAMES %s COLLATE %s", $c['charset'], $c['collation']
                ),
            ]);
        }
        return self::$pdo;
    }

    /**
     * Teste la connexion sans lever d exception (y compris si pdo_mysql est absent).
     */
    public static function isAvailable(): bool
    {
        try {
            self::connection()->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
