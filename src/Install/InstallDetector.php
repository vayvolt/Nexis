<?php

declare(strict_types=1);

namespace Nexis\Install;

use Nexis\Infrastructure\Database\ConnectionFactory;
use Nexis\Kernel\Config;
use Nexis\Kernel\Env;
use PDO;
use Throwable;

/**
 * Detects whether the installation wizard must run.
 * Lock file: storage/installed. Existing CLI installs are auto-locked when a site exists.
 *
 * Incomplete states (lock without .env, or .env that cannot reach an active site) reopen the installer.
 */
final class InstallDetector
{
    public function __construct(
        private string $rootPath,
    ) {
        $this->rootPath = rtrim($rootPath, '\\/');
    }

    public function lockPath(): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'installed';
    }

    public function envPath(): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . '.env';
    }

    public function isLocked(): bool
    {
        return is_file($this->lockPath());
    }

    public function markInstalled(string $note = 'web'): void
    {
        $dir = dirname($this->lockPath());
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $payload = json_encode([
            'installed_at' => gmdate('c'),
            'via' => $note,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        file_put_contents($this->lockPath(), $payload . "\n");
    }

    public function clearLock(): void
    {
        $lock = $this->lockPath();
        if (is_file($lock)) {
            @unlink($lock);
        }
    }

    public function needsInstall(): bool
    {
        if (!is_file($this->envPath())) {
            $this->clearLock();

            return true;
        }

        try {
            Env::load($this->rootPath);
            $config = Config::load($this->rootPath);
            $pdo = ConnectionFactory::create($config);
            if ($this->hasActiveSite($pdo)) {
                if (!$this->isLocked()) {
                    $this->markInstalled('auto');
                }

                return false;
            }

            $this->clearLock();

            return true;
        } catch (Throwable) {
            $this->clearLock();

            return true;
        }
    }

    private function hasActiveSite(PDO $pdo): bool
    {
        try {
            $count = $pdo->query(
                "SELECT COUNT(*) FROM sites WHERE deleted_at IS NULL AND status = 'active'",
            );
            if ($count === false) {
                return false;
            }

            return (int) $count->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
