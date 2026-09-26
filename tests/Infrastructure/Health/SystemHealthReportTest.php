<?php

declare(strict_types=1);

namespace Nexis\Tests\Infrastructure\Health;

use Nexis\Infrastructure\Database\DatabaseHealth;
use Nexis\Infrastructure\Health\SystemHealthReport;
use Nexis\Kernel\Config;
use Nexis\Queue\JobQueue;
use Nexis\Support\SystemClock;
use PDO;
use PHPUnit\Framework\TestCase;

final class SystemHealthReportTest extends TestCase
{
    public function testSnapshotIncludesQueueDiskAndPhp(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-health-report-' . bin2hex(random_bytes(4));
        mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0775, true);

        $pdo = new PDO('sqlite::memory:');
        $pdo->exec(
            'CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                available_at TEXT NOT NULL,
                reserved_at TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );
        $pdo->exec(
            'CREATE TABLE failed_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue TEXT NOT NULL,
                payload TEXT NOT NULL,
                exception TEXT NOT NULL,
                failed_at TEXT NOT NULL
            )',
        );

        $jobs = new JobQueue($pdo, new SystemClock());
        $jobs->push('mail', ['type' => 'health-test']);
        $jobs->push('default', ['type' => 'other']);

        $report = new SystemHealthReport(
            new Config(['app' => ['env' => 'testing', 'debug' => true]], $root),
            new DatabaseHealth(static fn (): PDO => $pdo),
            $jobs,
        );

        $snap = $report->snapshot();

        self::assertSame('ok', $snap['overall']);
        self::assertSame('ok', $snap['database']);
        self::assertSame('ok', $snap['storage']['status']);
        self::assertNotNull($snap['storage']['freeBytes']);
        self::assertSame(2, $snap['queue']['pending']);
        self::assertSame(0, $snap['queue']['failed']);
        self::assertCount(2, $snap['queue']['byQueue']);
        self::assertSame(PHP_VERSION, $snap['php']['version']);
        self::assertSame('testing', $snap['app']['env']);
        self::assertTrue($snap['app']['debug']);
        self::assertNotSame('', SystemHealthReport::formatBytes(1536));
    }

    public function testFormatBytes(): void
    {
        self::assertSame('0 B', SystemHealthReport::formatBytes(0));
        self::assertSame('1.5 KB', SystemHealthReport::formatBytes(1536));
    }
}
