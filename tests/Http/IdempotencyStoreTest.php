<?php

declare(strict_types=1);

namespace Nexis\Tests\Http;

use Nexis\Http\IdempotencyStore;
use Nexis\Site\SiteId;
use Nexis\Support\SystemClock;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;

final class IdempotencyStoreTest extends TestCase
{
    public function testRememberAndFind(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE idempotency_keys (
                id TEXT PRIMARY KEY,
                site_id TEXT NOT NULL,
                `key` TEXT NOT NULL,
                request_hash TEXT NOT NULL,
                response TEXT NULL,
                status_code INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                UNIQUE (site_id, `key`)
            )',
        );
        $store = new IdempotencyStore($pdo, new SystemClock());
        $siteId = new SiteId(Uuid::v7());
        $hash = IdempotencyStore::hashRequest('a', 'b');
        $store->remember($siteId, 'form-1', $hash, 302, ['type' => 'redirect', 'location' => '/ok']);

        $found = $store->find($siteId, 'form-1');
        self::assertNotNull($found);
        self::assertSame($hash, $found['request_hash']);
        self::assertSame(302, $found['status_code']);
        self::assertSame('redirect', $found['response']['type']);
    }
}
