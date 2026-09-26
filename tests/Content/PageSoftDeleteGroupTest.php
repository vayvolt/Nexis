<?php

declare(strict_types=1);

namespace Nexis\Tests\Content;

use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageStatus;
use Nexis\Content\PdoPageRepository;
use Nexis\Site\SiteId;
use Nexis\Support\SystemClock;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;

final class PageSoftDeleteGroupTest extends TestCase
{
    public function testSoftDeleteTranslationGroupRemovesAllLocales(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE pages (
                id TEXT PRIMARY KEY,
                site_id TEXT NOT NULL,
                parent_id TEXT NULL,
                translation_group_id TEXT NOT NULL,
                type TEXT NOT NULL,
                slug TEXT NOT NULL,
                path TEXT NOT NULL,
                locale TEXT NOT NULL,
                title TEXT NOT NULL,
                body_text TEXT NULL,
                status TEXT NOT NULL,
                scheduled_at TEXT NULL,
                scheduled_by TEXT NULL,
                unpublish_at TEXT NULL,
                unpublish_by TEXT NULL,
                published_revision_id TEXT NULL,
                published_snapshot_id TEXT NULL,
                search_text TEXT NULL,
                meta_title TEXT NULL,
                meta_description TEXT NULL,
                robots TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                deleted_at TEXT NULL
            )',
        );
        $pdo->exec('CREATE TABLE page_snapshots (id TEXT PRIMARY KEY, published_at TEXT, published_by TEXT)');
        $pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, display_name TEXT)');

        $repo = new PdoPageRepository($pdo, new SystemClock());
        $siteId = new SiteId(Uuid::v7());
        $groupId = Uuid::v7();
        $otherGroup = Uuid::v7();
        $deId = Uuid::v7();
        $enId = Uuid::v7();
        $otherId = Uuid::v7();
        $now = '2026-09-24 12:00:00.000';

        $insert = $pdo->prepare(
            'INSERT INTO pages (
                id, site_id, translation_group_id, type, slug, path, locale, title, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $insert->execute([$deId, $siteId->value, $groupId, 'page', 'home', '/', 'de', 'Start', 'draft', $now, $now]);
        $insert->execute([$enId, $siteId->value, $groupId, 'page', 'home', '/', 'en', 'Home', 'published', $now, $now]);
        $insert->execute([$otherId, $siteId->value, $otherGroup, 'page', 'other', '/other', 'de', 'Andere', 'draft', $now, $now]);

        $page = new Page(
            new PageId($deId),
            $siteId,
            $groupId,
            'de',
            'home',
            '/',
            'Start',
            null,
            PageStatus::Draft,
        );

        self::assertSame(2, $repo->softDeleteTranslationGroup($page));
        self::assertNull($repo->findById(new PageId($deId)));
        self::assertNull($repo->findById(new PageId($enId)));
        self::assertNotNull($repo->findById(new PageId($otherId)));

        $trash = $pdo->prepare('SELECT path FROM pages WHERE id = ?');
        $trash->execute([$deId]);
        self::assertSame('/.deleted/' . $deId, $trash->fetchColumn());
        self::assertFalse($repo->pathExists($siteId, 'de', '/'));
        self::assertFalse($repo->pathExists($siteId, 'en', '/'));
    }
}
