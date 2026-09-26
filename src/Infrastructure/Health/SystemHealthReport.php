<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Health;

use Nexis\Infrastructure\Database\DatabaseHealth;
use Nexis\Kernel\Config;
use Nexis\Kernel\Nexis;
use Nexis\Queue\JobQueue;
use Throwable;

/**
 * Aggregated health snapshot for admin dashboard and public /health.
 *
 * @phpstan-type HealthSnapshot array{
 *   overall: 'ok'|'degraded',
 *   database: 'ok'|'down',
 *   storage: array{status: 'ok'|'error', path: string, freeBytes: ?int, totalBytes: ?int, freeLabel: string, totalLabel: string, usedPercent: ?float},
 *   queue: array{status: 'ok'|'error', pending: ?int, reserved: ?int, failed: ?int, byQueue: list<array{queue: string, pending: int}>},
 *   php: array{version: string, sapi: string, memoryLimit: string, memoryUsage: string, memoryPeak: string, opcache: bool, extensions: list<string>},
 *   app: array{env: string, debug: bool, version: string, timezone: string}
 * }
 */
final class SystemHealthReport
{
    public function __construct(
        private Config $config,
        private DatabaseHealth $databaseHealth,
        private JobQueue $jobs,
    ) {
    }

    /**
     * @return HealthSnapshot
     */
    public function snapshot(): array
    {
        $database = $this->databaseHealth->status();
        $storage = $this->storage();
        $queue = $this->queue();
        $php = $this->php();
        $ok = $database === 'ok'
            && $storage['status'] === 'ok'
            && $queue['status'] === 'ok';

        return [
            'overall' => $ok ? 'ok' : 'degraded',
            'database' => $database === 'ok' ? 'ok' : 'down',
            'storage' => $storage,
            'queue' => $queue,
            'php' => $php,
            'app' => [
                'env' => $this->config->envName(),
                'debug' => $this->config->debug(),
                'version' => Nexis::VERSION,
                'timezone' => date_default_timezone_get(),
            ],
        ];
    }

    /**
     * @return array{status: 'ok'|'error', path: string, freeBytes: ?int, totalBytes: ?int, freeLabel: string, totalLabel: string, usedPercent: ?float}
     */
    private function storage(): array
    {
        $path = $this->config->rootPath . DIRECTORY_SEPARATOR . 'storage';
        $status = 'ok';
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            $status = 'error';
        } elseif (!is_writable($path)) {
            $status = 'error';
        } else {
            $probe = $path . DIRECTORY_SEPARATOR . '.health-' . bin2hex(random_bytes(4));
            if (file_put_contents($probe, 'ok') === false) {
                $status = 'error';
            } else {
                @unlink($probe);
            }
        }

        $free = @disk_free_space($path);
        $total = @disk_total_space($path);
        $freeBytes = $free !== false ? (int) $free : null;
        $totalBytes = $total !== false ? (int) $total : null;
        $usedPercent = null;
        if ($freeBytes !== null && $totalBytes !== null && $totalBytes > 0) {
            $usedPercent = round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1);
            if ($freeBytes < 50 * 1024 * 1024) {
                $status = 'error';
            }
        }

        return [
            'status' => $status,
            'path' => 'storage/',
            'freeBytes' => $freeBytes,
            'totalBytes' => $totalBytes,
            'freeLabel' => $freeBytes !== null ? self::formatBytes($freeBytes) : '—',
            'totalLabel' => $totalBytes !== null ? self::formatBytes($totalBytes) : '—',
            'usedPercent' => $usedPercent,
        ];
    }

    /**
     * @return array{status: 'ok'|'error', pending: ?int, reserved: ?int, failed: ?int, byQueue: list<array{queue: string, pending: int}>}
     */
    private function queue(): array
    {
        try {
            return [
                'status' => 'ok',
                'pending' => $this->jobs->pendingCount(),
                'reserved' => $this->jobs->reservedCount(),
                'failed' => $this->jobs->failedCount(),
                'byQueue' => $this->jobs->pendingByQueue(),
            ];
        } catch (Throwable) {
            return [
                'status' => 'error',
                'pending' => null,
                'reserved' => null,
                'failed' => null,
                'byQueue' => [],
            ];
        }
    }

    /**
     * @return array{version: string, sapi: string, memoryLimit: string, memoryUsage: string, memoryPeak: string, opcache: bool, extensions: list<string>}
     */
    private function php(): array
    {
        $needed = ['pdo', 'json', 'mbstring', 'intl', 'openssl', 'gd', 'sodium'];
        $ext = [];
        foreach ($needed as $name) {
            if (extension_loaded($name)) {
                $ext[] = $name;
            }
        }

        $opc = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;

        return [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memoryLimit' => (string) ini_get('memory_limit'),
            'memoryUsage' => self::formatBytes(memory_get_usage(true)),
            'memoryPeak' => self::formatBytes(memory_get_peak_usage(true)),
            'opcache' => is_array($opc) && ($opc['opcache_enabled'] ?? false) === true,
            'extensions' => $ext,
        ];
    }

    public static function formatBytes(int $bytes): string
    {
        $bytes = max(0, $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $value : number_format($value, 1)) . ' ' . $units[$i];
    }
}
