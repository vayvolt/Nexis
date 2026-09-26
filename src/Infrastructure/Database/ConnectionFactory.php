<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Database;

use Nexis\Kernel\Config;
use PDO;

final class ConnectionFactory
{
    public static function create(Config $config): PDO
    {
        $host = (string) $config->get('db.host', '127.0.0.1');
        $port = (string) $config->get('db.port', '3306');
        $database = (string) $config->get('db.database', 'nexis');
        $username = (string) $config->get('db.username', 'root');
        $password = (string) $config->get('db.password', '');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Connect without selecting a database (for CREATE DATABASE).
     */
    public static function createServer(Config $config): PDO
    {
        $host = (string) $config->get('db.host', '127.0.0.1');
        $port = (string) $config->get('db.port', '3306');
        $username = (string) $config->get('db.username', 'root');
        $password = (string) $config->get('db.password', '');

        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function ensureDatabase(Config $config): void
    {
        $database = (string) $config->get('db.database', 'nexis');
        if ($database === '' || preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            throw new \InvalidArgumentException('Ungültiger Datenbankname.');
        }
        $pdo = self::createServer($config);
        $pdo->exec(
            'CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        );
    }
}
