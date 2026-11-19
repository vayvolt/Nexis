<?php

declare(strict_types=1);

namespace Nexis\Tests\Content;

use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageStatus;
use Nexis\Content\PdoPageRepository;
use Nexis\Content\TrashEntry;
use Nexis\Site\SiteId;
use Nexis\Support\SystemClock;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;

final class PageTrashRestoreTest extends TestCase
{
    /**
     * @return array{0: PdoPageRepository, 1: PDO}
     */
    private function repo(): array
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
                restore_path TEXT NULL,
                restore_slug TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                deleted_at TEXT NULL
            )',
        );
        $pdo->exec('CREATE TABLE page_revisions (id TEXT PRIMARY KEY, page_id TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE page_snapshots (id TEXT PRIMARY KEY, page_id TEXT NOT NULL, published_at TEXT, published_by TEXT)');
        $pdo->exec('CREATE TABLE users (id TEXT PRIMARY KEY, display_name TEXT)');

        return [new PdoPageRepository($pdo, new SystemClock()), $pdo];
    }

    public function testSoftDeleteRemembersPathAndRestoreRecoversIt(): void
    {
        [$repo, $pdo] = $this->repo();
        $siteId = new SiteId(Uuid::v7());
        $groupId = Uuid::v7();
        $id = Uuid::v7();
        $now = '2026-11-12 12:00:00.000';

        $pdo->prepare(
            'INSERT INTO pages (
                id, site_id, translation_group_id, type, slug, path, locale, title, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        )->execute([$id, $siteId->value, $groupId, 'page', 'about', '/about', 'de', 'Über uns', 'published', $now, $now]);

        $page = new Page(
            new PageId($id),
            $siteId,
            $groupId,
            'de',
            'about',
            '/about',
            'Über uns',
            null,
            PageStatus::Published,
        );

        self::assertSame(1, $repo->softDeleteTranslationGroup($page));

        $trash = $repo->findTrashById(new PageId($id));
        self::assertInstanceOf(TrashEntry::class, $trash);
        self::assertSame('/about', $trash->restorePath);
        self::assertSame('about', $trash->restoreSlug);
        self::assertSame('/.deleted/' . $id, $trash->page->path);
        self::assertNull($repo->findById(new PageId($id)));

        self::assertSame(1, $repo->restoreTranslationGroup(new PageId($id)));
        self::assertNull($repo->findTrashById(new PageId($id)));

        $stmt = $pdo->prepare('SELECT path, slug, status, deleted_at FROM pages WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertNull($row['deleted_at']);
        self::assertSame('/about', $row['path']);
        self::assertSame('about', $row['slug']);
        self::assertSame(PageStatus::Draft->value, $row['status']);
    }

    public function testRestoreUsesFallbackWhenPathTaken(): void
    {
        [$repo, $pdo] = $this->repo();
        $siteId = new SiteId(Uuid::v7());
        $groupId = Uuid::v7();
        $id = Uuid::v7();
        $other = Uuid::v7();
        $now = '2026-11-12 12:00:00.000';

        $insert = $pdo->prepare(
            'INSERT INTO pages (
                id, site_id, translation_group_id, type, slug, path, locale, title, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $insert->execute([$id, $siteId->value, $groupId, 'page', 'about', '/about', 'de', 'Über uns', 'draft', $now, $now]);

        $page = new Page(new PageId($id), $siteId, $groupId, 'de', 'about', '/about', 'Über uns', null, PageStatus::Draft);
        $repo->softDeleteTranslationGroup($page);

        $insert->execute([$other, $siteId->value, Uuid::v7(), 'page', 'about', '/about', 'de', 'Neu', 'draft', $now, $now]);

        self::assertSame(1, $repo->restoreTranslationGroup(new PageId($id)));
        $restored = $repo->findById(new PageId($id));
        self::assertNotNull($restored);
        self::assertNotSame('/about', $restored->path);
        self::assertStringContainsString('about', $restored->slug);
    }

    public function testCountTrashCountsTranslationGroupsNotLocales(): void
    {
        [$repo, $pdo] = $this->repo();
        $siteId = new SiteId(Uuid::v7());
        $groupId = Uuid::v7();
        $deId = Uuid::v7();
        $enId = Uuid::v7();
        $now = '2026-11-12 12:00:00.000';

        $insert = $pdo->prepare(
            'INSERT INTO pages (
                id, site_id, translation_group_id, type, slug, path, locale, title, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $insert->execute([$deId, $siteId->value, $groupId, 'page', 'about', '/about', 'de', 'Über uns', 'draft', $now, $now]);
        $insert->execute([$enId, $siteId->value, $groupId, 'page', 'about', '/about', 'en', 'About', 'draft', $now, $now]);

        $page = new Page(new PageId($deId), $siteId, $groupId, 'de', 'about', '/about', 'Über uns', null, PageStatus::Draft);
        $repo->softDeleteTranslationGroup($page);

        self::assertSame(1, $repo->countTrashBySite($siteId));
        $groups = $repo->listTrashGrouped($siteId, 'de', 'de');
        self::assertCount(1, $groups);
        self::assertCount(2, $groups[0]['byLocale']);
    }

    public function testEmptyTrashPurgesAllGroups(): void
    {
        [$repo, $pdo] = $this->repo();
        $siteId = new SiteId(Uuid::v7());
        $now = '2026-11-12 12:00:00.000';
        $insert = $pdo->prepare(
            'INSERT INTO pages (
                id, site_id, translation_group_id, type, slug, path, locale, title, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );

        $groupA = Uuid::v7();
        $idA = Uuid::v7();
        $insert->execute([$idA, $siteId->value, $groupA, 'page', 'a', '/a', 'de', 'A', 'draft', $now, $now]);
        $repo->softDeleteTranslationGroup(new Page(new PageId($idA), $siteId, $groupA, 'de', 'a', '/a', 'A', null, PageStatus::Draft));

        $groupB = Uuid::v7();
        $idBDe = Uuid::v7();
        $idBEn = Uuid::v7();
        $insert->execute([$idBDe, $siteId->value, $groupB, 'page', 'b', '/b', 'de', 'B', 'draft', $now, $now]);
        $insert->execute([$idBEn, $siteId->value, $groupB, 'page', 'b', '/b', 'en', 'B', 'draft', $now, $now]);
        $repo->softDeleteTranslationGroup(new Page(new PageId($idBDe), $siteId, $groupB, 'de', 'b', '/b', 'B', null, PageStatus::Draft));

        self::assertSame(2, $repo->countTrashBySite($siteId));
        self::assertSame(2, $repo->emptyTrash($siteId));
        self::assertSame(0, $repo->countTrashBySite($siteId));
        self::assertSame(0, $this->countRows($pdo, 'pages'));
    }

    public function testPurgeRemovesPageAndRelatedRows(): void
    {
        [$repo, $pdo] = $this->repo();
        $siteId = new SiteId(Uuid::v7());
        $groupId = Uuid::v7();
        $id = Uuid::v7();
        $now = '2026-11-12 12:00:00.000';

        $pdo->prepare(
            'INSERT INTO pages (
                id, site_id, translation_group_id, type, slug, path, locale, title, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        )->execute([$id, $siteId->value, $groupId, 'page', 'gone', '/gone', 'de', 'Weg', 'draft', $now, $now]);
        $pdo->prepare('INSERT INTO page_revisions (id, page_id) VALUES (?, ?)')->execute([Uuid::v7(), $id]);
        $pdo->prepare('INSERT INTO page_snapshots (id, page_id) VALUES (?, ?)')->execute([Uuid::v7(), $id]);

        $page = new Page(new PageId($id), $siteId, $groupId, 'de', 'gone', '/gone', 'Weg', null, PageStatus::Draft);
        $repo->softDeleteTranslationGroup($page);
        self::assertSame(1, $repo->purgeTranslationGroup(new PageId($id)));
        self::assertNull($repo->findTrashById(new PageId($id)));
        self::assertSame(0, $this->countRows($pdo, 'pages'));
        self::assertSame(0, $this->countRows($pdo, 'page_revisions'));
        self::assertSame(0, $this->countRows($pdo, 'page_snapshots'));
    }

    public function testPurgeWorksWhenPublishedRefsAlreadyNull(): void
    {
        [$repo, $pdo] = $this->repo();
        $siteId = new SiteId(Uuid::v7());
        $groupId = Uuid::v7();
        $id = Uuid::v7();
        $now = '2026-11-12 12:00:00.000';

        $pdo->prepare(
            'INSERT INTO pages (
                id, site_id, translation_group_id, type, slug, path, locale, title, status,
                published_revision_id, published_snapshot_id, created_at, updated_at, deleted_at,
                restore_path, restore_slug
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?)',
        )->execute([
            $id,
            $siteId->value,
            $groupId,
            'page',
            'deleted-abc',
            '/.deleted/' . $id,
            'de',
            'Weg',
            'draft',
            $now,
            $now,
            $now,
            '/weg',
            'weg',
        ]);
        $pdo->prepare('INSERT INTO page_revisions (id, page_id) VALUES (?, ?)')->execute([Uuid::v7(), $id]);

        self::assertSame(1, $repo->purgeTranslationGroup(new PageId($id)));
        self::assertSame(0, $this->countRows($pdo, 'pages'));
        self::assertSame(0, $this->countRows($pdo, 'page_revisions'));
    }

    private function countRows(PDO $pdo, string $table): int
    {
        $stmt = $pdo->query('SELECT COUNT(*) FROM ' . $table);
        self::assertInstanceOf(\PDOStatement::class, $stmt);

        return (int) $stmt->fetchColumn();
    }
}
