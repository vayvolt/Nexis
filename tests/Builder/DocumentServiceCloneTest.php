<?php

declare(strict_types=1);

namespace Nexis\Tests\Builder;

use Nexis\Builder\BlockRegistry;
use Nexis\Builder\Core\HeadingBlock;
use Nexis\Builder\Core\SectionBlock;
use Nexis\Builder\Core\TextBlock;
use Nexis\Builder\DocumentService;
use Nexis\Builder\DocumentValidator;
use Nexis\Builder\PageRevision;
use Nexis\Builder\RevisionId;
use Nexis\Builder\RevisionRepository;
use Nexis\Content\PageId;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DocumentServiceCloneTest extends TestCase
{
    public function testCloneSubtreeRegeneratesAllIds(): void
    {
        $childId = Uuid::v7();
        $rootId = Uuid::v7();
        $node = [
            'id' => $rootId,
            'type' => 'core/section',
            'props' => ['width' => 'wide', 'padding' => 'lg'],
            'children' => [
                [
                    'id' => $childId,
                    'type' => 'core/heading',
                    'props' => ['text' => 'Title', 'level' => 2],
                ],
            ],
        ];

        $registry = new BlockRegistry([new SectionBlock(), new HeadingBlock(), new TextBlock()]);
        $service = new DocumentService(
            new CloneTestRevisionRepository(),
            new DocumentValidator($registry),
            new CloneTestClock(),
        );

        $cloned = $service->cloneSubtree($node);

        self::assertNotSame($rootId, $cloned['id']);
        self::assertIsString($cloned['id']);
        self::assertArrayHasKey('children', $cloned);
        self::assertIsArray($cloned['children']);
        self::assertCount(1, $cloned['children']);
        self::assertNotSame($childId, $cloned['children'][0]['id']);
        self::assertSame('Title', $cloned['children'][0]['props']['text']);
    }
}

final class CloneTestRevisionRepository implements RevisionRepository
{
    public function findById(RevisionId $id): ?PageRevision
    {
        return null;
    }

    public function latestForPage(PageId $pageId): ?PageRevision
    {
        return null;
    }

    public function listForPage(PageId $pageId, int $limit = 50): array
    {
        return [];
    }

    public function save(PageRevision $revision): void
    {
    }
}

final class CloneTestClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-08 12:00:00.000');
    }
}
