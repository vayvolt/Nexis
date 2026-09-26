<?php

declare(strict_types=1);

namespace Nexis\Tests\Builder;

use Nexis\Auth\UserId;
use Nexis\Builder\BlockDocument;
use Nexis\Builder\BlockRegistry;
use Nexis\Builder\BlockRenderer;
use Nexis\Builder\Core\ButtonBlock;
use Nexis\Builder\Core\ColumnsBlock;
use Nexis\Builder\Core\HeadingBlock;
use Nexis\Builder\Core\ImageBlock;
use Nexis\Builder\Core\SectionBlock;
use Nexis\Builder\Core\TextBlock;
use Nexis\Builder\DocumentConflictException;
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

final class DocumentServiceTest extends TestCase
{
    public function testOptimisticLockRejectsStaleHash(): void
    {
        $pageId = new PageId(Uuid::v7());
        $repo = new InMemoryRevisionRepository();
        $registry = new BlockRegistry([
            new SectionBlock(),
            new HeadingBlock(),
            new TextBlock(),
            new ImageBlock(),
            new ButtonBlock(),
            new ColumnsBlock(),
        ]);
        $service = new DocumentService($repo, new DocumentValidator($registry), new FixedClock());
        $doc = BlockDocument::empty()->toArray();
        $first = $service->save($pageId, $doc, null, new UserId(Uuid::v7()));

        $this->expectException(DocumentConflictException::class);
        $service->save($pageId, $doc, 'deadbeef', new UserId(Uuid::v7()));
        unset($first);
    }

    public function testValidatorAcceptsCoreDocument(): void
    {
        $registry = new BlockRegistry([
            new SectionBlock(),
            new HeadingBlock(),
            new TextBlock(),
            new ImageBlock(),
            new ButtonBlock(),
            new ColumnsBlock(),
        ]);
        $validator = new DocumentValidator($registry);
        $document = [
            'schemaVersion' => 1,
            'root' => [
                'id' => Uuid::v7(),
                'type' => 'core/section',
                'props' => ['width' => 'wide', 'padding' => 'lg'],
                'children' => [
                    [
                        'id' => Uuid::v7(),
                        'type' => 'core/heading',
                        'props' => ['text' => 'Hi', 'level' => 1],
                    ],
                ],
            ],
        ];
        self::assertSame([], $validator->validate($document));
        $html = (new BlockRenderer($registry))->renderDocument($document, ['basePath' => '']);
        self::assertStringContainsString('Hi', $html);
    }
}

final class InMemoryRevisionRepository implements RevisionRepository
{
    /** @var array<string, list<PageRevision>> */
    private array $byPage = [];

    public function findById(RevisionId $id): ?PageRevision
    {
        foreach ($this->byPage as $list) {
            foreach ($list as $rev) {
                if ($rev->id->equals($id)) {
                    return $rev;
                }
            }
        }

        return null;
    }

    public function latestForPage(PageId $pageId): ?PageRevision
    {
        $list = $this->byPage[$pageId->value] ?? [];

        return $list[0] ?? null;
    }

    public function listForPage(PageId $pageId, int $limit = 50): array
    {
        return array_slice($this->byPage[$pageId->value] ?? [], 0, $limit);
    }

    public function save(PageRevision $revision): void
    {
        $list = $this->byPage[$revision->pageId->value] ?? [];
        array_unshift($list, $revision);
        $this->byPage[$revision->pageId->value] = $list;
    }
}

final class FixedClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-08 12:00:00.000');
    }
}
