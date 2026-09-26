<?php

declare(strict_types=1);

namespace Nexis\Tests\Content;

use Nexis\Content\PageEditorialItem;
use Nexis\Content\PageEditorialItemId;
use Nexis\Content\PageId;
use Nexis\Content\PdoPageEditorialRepository;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;

final class PageEditorialRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoPageEditorialRepository $repo;
    private PageId $pageId;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE users (
                id TEXT PRIMARY KEY,
                display_name TEXT NOT NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE page_editorial_items (
                id TEXT NOT NULL PRIMARY KEY,
                page_id TEXT NOT NULL,
                kind TEXT NOT NULL,
                body TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT \'open\',
                created_by TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                resolved_at TEXT NULL,
                resolved_by TEXT NULL
            )',
        );
        $this->repo = new PdoPageEditorialRepository($this->pdo);
        $this->pageId = new PageId(Uuid::v7());
        $userId = Uuid::v7();
        $this->pdo->prepare('INSERT INTO users (id, display_name) VALUES (?, ?)')
            ->execute([$userId, 'Ada Editor']);
        $this->userId = $userId;
    }

    private string $userId;

    public function testSaveCommentAndList(): void
    {
        $now = new \DateTimeImmutable('2026-09-24 12:00:00', new \DateTimeZone('UTC'));
        $item = new PageEditorialItem(
            new PageEditorialItemId(Uuid::v7()),
            $this->pageId,
            PageEditorialItem::KIND_COMMENT,
            'Bitte Hero kürzen',
            PageEditorialItem::STATUS_OPEN,
            $this->userId,
            $now,
            $now,
        );
        $this->repo->save($item);

        $list = $this->repo->listForPage($this->pageId);
        self::assertCount(1, $list);
        self::assertSame('Bitte Hero kürzen', $list[0]->body);
        self::assertFalse($list[0]->isTask);
        self::assertSame('Ada Editor', $list[0]->authorName);
    }

    public function testToggleTaskAndDelete(): void
    {
        $now = new \DateTimeImmutable('2026-09-24 12:00:00', new \DateTimeZone('UTC'));
        $id = new PageEditorialItemId(Uuid::v7());
        $item = new PageEditorialItem(
            $id,
            $this->pageId,
            PageEditorialItem::KIND_TASK,
            'Alt-Text prüfen',
            PageEditorialItem::STATUS_OPEN,
            $this->userId,
            $now,
            $now,
        );
        $this->repo->save($item);

        $loaded = $this->repo->findById($id, $this->pageId);
        self::assertNotNull($loaded);
        self::assertTrue($loaded->isTask);
        self::assertFalse($loaded->isDone);

        $later = $now->modify('+1 hour');
        $loaded->markDone($this->userId, $later);
        $this->repo->save($loaded);

        $done = $this->repo->findById($id, $this->pageId);
        self::assertNotNull($done);
        self::assertTrue($done->isDone);
        self::assertNotNull($done->resolvedAt);

        self::assertTrue($this->repo->delete($id, $this->pageId));
        self::assertNull($this->repo->findById($id, $this->pageId));
    }

    public function testRejectsEmptyBody(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PageEditorialItem(
            new PageEditorialItemId(Uuid::v7()),
            $this->pageId,
            PageEditorialItem::KIND_COMMENT,
            '   ',
            PageEditorialItem::STATUS_OPEN,
            null,
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }
}
