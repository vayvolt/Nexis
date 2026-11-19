<?php

declare(strict_types=1);

namespace Nexis\Install;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Verifies MySQL/MariaDB connectivity and privileges before the installer writes .env.
 */
final class DatabasePreflight
{
    private const PROBE_TABLE = '_nexis_install_probe';

    public function verify(
        string $host,
        string $port,
        string $database,
        string $username,
        string $password,
    ): DatabasePreflightResult {
        $host = trim($host);
        $port = trim($port);
        $database = trim($database);
        $username = trim($username);

        if ($host === '') {
            return DatabasePreflightResult::failure('connection', 'Host is empty.');
        }
        if ($port === '' || !ctype_digit($port)) {
            return DatabasePreflightResult::failure('connection', 'Port must be numeric.');
        }
        if ($database === '' || preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            return DatabasePreflightResult::failure('invalid_name');
        }
        if ($username === '') {
            return DatabasePreflightResult::failure('connection', 'Username is empty.');
        }

        try {
            $server = $this->connectServer($host, $port, $username, $password);
        } catch (Throwable $e) {
            return DatabasePreflightResult::failure('connection', $this->summarize($e));
        }

        if (!$this->databaseExists($server, $database)) {
            try {
                $server->exec(
                    'CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                );
            } catch (Throwable $e) {
                return DatabasePreflightResult::failure('create_database', $this->summarize($e));
            }
        }

        try {
            $pdo = $this->connectDatabase($host, $port, $database, $username, $password);
        } catch (Throwable $e) {
            return DatabasePreflightResult::failure('select_database', $this->summarize($e));
        }

        try {
            $this->probePrivileges($pdo);
        } catch (Throwable $e) {
            return DatabasePreflightResult::failure('privileges', $this->summarize($e));
        }

        return DatabasePreflightResult::success();
    }

    public function connect(
        string $host,
        string $port,
        string $database,
        string $username,
        string $password,
    ): PDO {
        return $this->connectDatabase(trim($host), trim($port), trim($database), trim($username), $password);
    }

    public function inspectExisting(PDO $pdo): DatabaseExistingContent
    {
        $tableCount = 0;
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');
        if ($tables !== false) {
            $tableCount = count($tables->fetchAll(PDO::FETCH_NUM));
        }

        $siteCount = 0;
        if ($this->tableExists($pdo, 'sites')) {
            $stmt = $pdo->query('SELECT COUNT(*) FROM sites');
            $siteCount = $stmt !== false ? (int) $stmt->fetchColumn() : 0;
        }

        $migrationCount = 0;
        if ($this->tableExists($pdo, 'schema_migrations')) {
            $stmt = $pdo->query('SELECT COUNT(*) FROM schema_migrations');
            $migrationCount = $stmt !== false ? (int) $stmt->fetchColumn() : 0;
        }

        return new DatabaseExistingContent($tableCount, $siteCount, $migrationCount);
    }

    /**
     * Drops every base table in the current database (for confirmed reinstall).
     */
    public function wipeAllTables(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');
            if ($tables === false) {
                throw new RuntimeException('Could not list tables for wipe.');
            }
            foreach ($tables->fetchAll(PDO::FETCH_NUM) as $row) {
                $name = (string) ($row[0] ?? '');
                if ($name === '' || preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
                    continue;
                }
                $pdo->exec('DROP TABLE IF EXISTS `' . $name . '`');
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :name LIMIT 1',
        );
        $stmt->execute(['name' => $table]);

        return $stmt->fetchColumn() !== false;
    }

    private function connectServer(string $host, string $port, string $username, string $password): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);

        return new PDO($dsn, $username, $password, $this->pdoOptions());
    }

    private function connectDatabase(
        string $host,
        string $port,
        string $database,
        string $username,
        string $password,
    ): PDO {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

        return new PDO($dsn, $username, $password, $this->pdoOptions());
    }

    /**
     * @return array<int, mixed>
     */
    private function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];
    }

    private function databaseExists(PDO $server, string $database): bool
    {
        $stmt = $server->prepare(
            'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :name LIMIT 1',
        );
        $stmt->execute(['name' => $database]);

        return $stmt->fetchColumn() !== false;
    }

    private function probePrivileges(PDO $pdo): void
    {
        $table = self::PROBE_TABLE;
        $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        $pdo->exec(
            'CREATE TABLE `' . $table . '` (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                label VARCHAR(32) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
        $pdo->exec("INSERT INTO `{$table}` (label) VALUES ('ok')");
        $select = $pdo->query("SELECT id, label FROM `{$table}` LIMIT 1");
        if ($select === false || $select->fetch() === false) {
            throw new RuntimeException('SELECT probe failed.');
        }
        $pdo->exec("UPDATE `{$table}` SET label = 'updated' WHERE id = 1");
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN probe_extra TINYINT NOT NULL DEFAULT 0");
        $pdo->exec("CREATE INDEX idx_nexis_install_probe_label ON `{$table}` (label)");
        $pdo->exec("DELETE FROM `{$table}` WHERE id = 1");
        $pdo->exec('DROP TABLE `' . $table . '`');
    }

    private function summarize(Throwable $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return $e::class;
        }
        $message = preg_replace('/^SQLSTATE\[[^\]]+\]\s*/', '', $message) ?? $message;
        $message = preg_replace('/^\[\w+\]\s*\[[^\]]*\]\s*/', '', $message) ?? $message;

        return substr($message, 0, 400);
    }
}
