<?php

declare(strict_types=1);

namespace Nexis\Tests\Queue;

use Nexis\Queue\JobQueue;
use Nexis\Support\SystemClock;
use PDO;
use PHPUnit\Framework\TestCase;

final class JobQueueFailTest extends TestCase
{
    public function testFailMovesRowToFailedJobs(): void
    {
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

        $queue = new JobQueue($pdo, new SystemClock());
        $queue->push('mail', ['type' => 'test', 'to' => 'a@b.test']);
        $jobs = $queue->reserve('mail', 1);
        self::assertCount(1, $jobs);
        $queue->fail($jobs[0]['id'], 'mail', $jobs[0]['payload'], 'boom');

        self::assertSame(0, $queue->pendingCount());
        self::assertSame(1, $queue->failedCount());
        self::assertSame(1, $queue->failedCount('mail'));
        self::assertSame(0, $queue->failedCount('other'));
        $countStmt = $pdo->query('SELECT COUNT(*) FROM failed_jobs');
        self::assertNotFalse($countStmt);
        self::assertSame(1, (int) $countStmt->fetchColumn());
        $excStmt = $pdo->query('SELECT exception FROM failed_jobs LIMIT 1');
        self::assertNotFalse($excStmt);
        self::assertSame('boom', (string) $excStmt->fetchColumn());
    }

    public function testPendingByQueueAndReservedCount(): void
    {
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

        $queue = new JobQueue($pdo, new SystemClock());
        $queue->push('mail', ['type' => 'a']);
        $queue->push('mail', ['type' => 'b']);
        $queue->push('default', ['type' => 'c']);
        $reserved = $queue->reserve('mail', 1);

        self::assertCount(1, $reserved);
        self::assertSame(1, $queue->reservedCount());
        self::assertSame(3, $queue->pendingCount());
        $byQueue = $queue->pendingByQueue();
        self::assertSame(
            [
                ['queue' => 'mail', 'pending' => 2],
                ['queue' => 'default', 'pending' => 1],
            ],
            $byQueue,
        );
    }
}
