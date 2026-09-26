<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use Nexis\Infrastructure\Database\Migrator;
use PDO;
use RuntimeException;

final class PluginMigrator
{
    private static bool $registryReady = false;

    public function __construct(
        private PDO $pdo,
    ) {
    }

    /**
     * @return list<string>
     */
    public function migrate(PluginManifest $manifest): array
    {
        $dir = $manifest->directory . DIRECTORY_SEPARATOR . $manifest->migrationsDir;
        if (!is_dir($dir)) {
            return [];
        }

        $this->ensureRegistry();
        $applied = [];
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $version = 'plugin:' . $manifest->id . ':' . basename($file);
            if ($this->isApplied($version)) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Plugin-Migration unlesbar: ' . $file);
            }
            foreach (Migrator::splitStatements($sql) as $statement) {
                $this->pdo->exec($statement);
            }
            $stmt = $this->pdo->prepare(
                'INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)',
            );
            $stmt->execute([
                'version' => $version,
                'applied_at' => gmdate('Y-m-d H:i:s.v'),
            ]);
            $applied[] = $version;
        }

        return $applied;
    }

    private function isApplied(string $version): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :version LIMIT 1');
        $stmt->execute(['version' => $version]);

        return (bool) $stmt->fetchColumn();
    }

    private function ensureRegistry(): void
    {
        if (self::$registryReady) {
            return;
        }
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(190) NOT NULL,
                applied_at DATETIME(3) NOT NULL,
                PRIMARY KEY (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
        self::$registryReady = true;
    }
}
