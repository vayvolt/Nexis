<?php

declare(strict_types=1);

namespace Nexis\Tests\Cache;

use Nexis\Cache\PageCache;
use Nexis\Site\SiteId;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;

final class PageCacheTest extends TestCase
{
    public function testPutGetAndInvalidate(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE cache_pages (
                id TEXT PRIMARY KEY,
                site_id TEXT NOT NULL,
                cache_key TEXT NOT NULL,
                body_hash TEXT NOT NULL,
                stored_at TEXT NOT NULL,
                expires_at TEXT NULL,
                UNIQUE (site_id, cache_key)
            )',
        );
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bk-cache-' . bin2hex(random_bytes(4));
        $cache = new PageCache($pdo, new class implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-09-08 12:00:00.000', new DateTimeZone('UTC'));
            }
        }, $dir, 3600);

        $siteId = new SiteId(Uuid::v7());
        $key = $cache->key('de', '/', 'snap1');
        self::assertNull($cache->get($siteId, $key));
        $cache->put($siteId, $key, '<html>hi</html>');
        self::assertSame('<html>hi</html>', $cache->get($siteId, $key));
        $cache->invalidateSite($siteId);
        self::assertNull($cache->get($siteId, $key));
    }

    public function testExpiryUsesUtcEvenWhenDefaultTimezoneIsNotUtc(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');
        try {
            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec(
                'CREATE TABLE cache_pages (
                    id TEXT PRIMARY KEY,
                    site_id TEXT NOT NULL,
                    cache_key TEXT NOT NULL,
                    body_hash TEXT NOT NULL,
                    stored_at TEXT NOT NULL,
                    expires_at TEXT NULL,
                    UNIQUE (site_id, cache_key)
                )',
            );
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bk-cache-' . bin2hex(random_bytes(4));
            $now = new DateTimeImmutable('2026-09-26 13:30:00.000', new DateTimeZone('UTC'));
            $cache = new PageCache($pdo, new class ($now) implements Clock {
                public function __construct(private DateTimeImmutable $now)
                {
                }

                public function now(): DateTimeImmutable
                {
                    return $this->now;
                }
            }, $dir, 3600);

            $siteId = new SiteId(Uuid::v7());
            $key = $cache->key('de', '/', 'snap-tz');
            $cache->put($siteId, $key, '<html>tz</html>');
            self::assertSame('<html>tz</html>', $cache->get($siteId, $key));
        } finally {
            date_default_timezone_set($previous);
        }
    }
}
