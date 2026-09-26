<?php

declare(strict_types=1);

namespace Nexis\Builder;

use Nexis\Audit\AuditLogger;
use Nexis\Auth\UserId;
use Nexis\Cache\PageCache;
use Nexis\Content\Page;
use Nexis\Content\PageRepository;
use Nexis\Content\PageStatus;
use Nexis\Event\EventDispatcher;
use Nexis\Event\PagePublished;
use Nexis\Event\PageReviewRejected;
use Nexis\Event\PageSubmittedForReview;
use Nexis\Event\PageUnpublished;
use Nexis\Media\MediaId;
use Nexis\Media\MediaRepository;
use Nexis\Site\SiteId;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use PDO;
use RuntimeException;

final class PublishService
{
    public function __construct(
        private PDO $pdo,
        private RevisionRepository $revisions,
        private SnapshotRepository $snapshots,
        private PageRepository $pages,
        private DocumentService $documents,
        private Clock $clock,
        private PageCache $cache,
        private AuditLogger $audit,
        private EventDispatcher $events,
        private MediaRepository $media,
    ) {
    }

    public function publish(Page $page, ?RevisionId $revisionId, UserId $actor, string $basePath = ''): PageSnapshot
    {
        $revision = $revisionId !== null
            ? $this->revisions->findById($revisionId)
            : $this->revisions->latestForPage($page->id);
        if ($revision === null || !$revision->pageId->equals($page->id)) {
            throw new RuntimeException('Keine Revision zum Veröffentlichen.');
        }

        $payload = $this->canonicalize($revision->document, $basePath, $page->siteId);
        $searchText = $this->extractSearchText($payload);
        $snapshot = new PageSnapshot(
            new SnapshotId(Uuid::v7()),
            $page->id,
            $revision->id,
            $payload,
            DocumentHash::of($payload),
            $actor,
            $this->clock->now(),
        );

        $this->pdo->beginTransaction();
        try {
            $this->snapshots->save($snapshot);
            $published = new Page(
                $page->id,
                $page->siteId,
                $page->translationGroupId,
                $page->locale,
                $page->slug,
                $page->path,
                $page->title,
                $page->bodyText,
                PageStatus::Published,
                $revision->id,
                $snapshot->id,
                $searchText,
                $page->metaTitle,
                $page->metaDescription,
                $page->robots,
                null,
                null,
                $page->type,
                null,
                null,
                null,
                $page->unpublishAt,
                $page->unpublishBy,
                $page->focusKeyword,
            );
            $this->pages->save($published);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $this->cache->invalidateSite($page->siteId);
        $this->audit->log(
            'content.page.publish',
            $page->siteId,
            $actor,
            'page',
            $page->id->value,
            ['snapshot_id' => $snapshot->id->value],
        );
        $this->events->dispatch(new PagePublished(
            $page->siteId,
            $page->id,
            $snapshot->id,
            $page->locale,
            $page->path,
            $page->title,
            $actor,
        ));

        return $snapshot;
    }

    public function submitForReview(Page $page, UserId $actor): Page
    {
        if ($page->status === PageStatus::InReview) {
            return $page;
        }
        if ($page->status === PageStatus::Published || $page->status === PageStatus::Scheduled) {
            // Live pages can still request review of the current draft without taking them offline.
        }

        $this->documents->ensureDraft($page->id, $actor);

        $review = new Page(
            $page->id,
            $page->siteId,
            $page->translationGroupId,
            $page->locale,
            $page->slug,
            $page->path,
            $page->title,
            $page->bodyText,
            PageStatus::InReview,
            $page->publishedRevisionId,
            $page->publishedSnapshotId,
            $page->searchText,
            $page->metaTitle,
            $page->metaDescription,
            $page->robots,
            $page->scheduledAt,
            $page->scheduledBy,
            $page->type,
            null,
            null,
            null,
            $page->unpublishAt,
            $page->unpublishBy,
            $page->focusKeyword,
        );
        $this->pages->save($review);
        $this->audit->log(
            'content.page.submit_review',
            $page->siteId,
            $actor,
            'page',
            $page->id->value,
            [],
        );
        $this->events->dispatch(new PageSubmittedForReview(
            $page->siteId,
            $page->id,
            $page->locale,
            $page->path,
            $page->title,
            $actor,
        ));

        return $review;
    }

    public function rejectReview(Page $page, UserId $actor): Page
    {
        if ($page->status !== PageStatus::InReview) {
            return $page;
        }

        $status = $page->publishedSnapshotId !== null
            ? PageStatus::Published
            : ($page->scheduledAt !== null ? PageStatus::Scheduled : PageStatus::Draft);

        $rejected = new Page(
            $page->id,
            $page->siteId,
            $page->translationGroupId,
            $page->locale,
            $page->slug,
            $page->path,
            $page->title,
            $page->bodyText,
            $status,
            $page->publishedRevisionId,
            $page->publishedSnapshotId,
            $page->searchText,
            $page->metaTitle,
            $page->metaDescription,
            $page->robots,
            $page->scheduledAt,
            $page->scheduledBy,
            $page->type,
            null,
            null,
            null,
            $page->unpublishAt,
            $page->unpublishBy,
            $page->focusKeyword,
        );
        $this->pages->save($rejected);
        $this->audit->log(
            'content.page.reject_review',
            $page->siteId,
            $actor,
            'page',
            $page->id->value,
            ['restored_status' => $status->value],
        );
        $this->events->dispatch(new PageReviewRejected(
            $page->siteId,
            $page->id,
            $page->locale,
            $page->path,
            $page->title,
            $actor,
        ));

        return $rejected;
    }

    public function unpublish(Page $page, UserId $actor): Page
    {
        if ($page->publishedSnapshotId === null && $page->status === PageStatus::Draft) {
            return $page;
        }

        $draft = new Page(
            $page->id,
            $page->siteId,
            $page->translationGroupId,
            $page->locale,
            $page->slug,
            $page->path,
            $page->title,
            $page->bodyText,
            PageStatus::Draft,
            null,
            null,
            $page->searchText,
            $page->metaTitle,
            $page->metaDescription,
            $page->robots,
            null,
            null,
            $page->type,
            null,
            null,
            null,
            null,
            null,
            $page->focusKeyword,
        );

        $this->pages->save($draft);
        $this->cache->invalidateSite($page->siteId);
        $this->audit->log(
            'content.page.unpublish',
            $page->siteId,
            $actor,
            'page',
            $page->id->value,
            [],
        );
        $this->events->dispatch(new PageUnpublished(
            $page->siteId,
            $page->id,
            $page->locale,
            $page->path,
            $page->title,
            $actor,
        ));

        return $draft;
    }

    public function revert(Page $page, RevisionId $revisionId, UserId $actor): PageRevision
    {
        $revision = $this->revisions->findById($revisionId);
        if ($revision === null || !$revision->pageId->equals($page->id)) {
            throw new RuntimeException('Revision nicht gefunden.');
        }

        $latest = $this->revisions->latestForPage($page->id);

        $created = $this->documents->save(
            $page->id,
            $revision->document,
            $latest?->documentHash,
            $actor,
            'Revert auf ' . $revision->id->value,
        );
        $this->audit->log(
            'content.page.revert',
            $page->siteId,
            $actor,
            'page',
            $page->id->value,
            ['revision_id' => $revisionId->value],
        );

        return $created;
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function canonicalize(array $document, string $basePath, SiteId $siteId): array
    {
        $doc = BlockDocument::fromArray($document);
        $root = $this->resolveMediaUrls($doc->root, $basePath, $siteId);

        return (new BlockDocument($doc->schemaVersion, $root))->toArray();
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function resolveMediaUrls(array $node, string $basePath, SiteId $siteId): array
    {
        $type = (string) ($node['type'] ?? '');
        $props = $node['props'] ?? [];
        if ($type === 'core/image' && is_array($props)) {
            $assetId = (string) ($props['assetId'] ?? '');
            if ($assetId !== '') {
                $props['src'] = $basePath . '/media/' . $assetId;
                try {
                    $asset = $this->media->findById(new MediaId($assetId), $siteId);
                } catch (\InvalidArgumentException) {
                    $asset = null;
                }
                if ($asset !== null) {
                    if ($this->media->hasVariant($asset->id, 'webp')) {
                        $props['srcWebp'] = $basePath . '/media/' . $assetId . '/webp';
                    }
                    $alt = (string) ($props['alt'] ?? '');
                    if ($alt === '' && is_string($asset->altText) && $asset->altText !== '') {
                        $props['alt'] = $asset->altText;
                    }
                    $props['focusX'] = (string) $asset->focusX;
                    $props['focusY'] = (string) $asset->focusY;
                }
                $node['props'] = $props;
            }
        }
        if ($type === 'core/gallery' && is_array($props)) {
            $ids = \Nexis\Builder\Core\GalleryBlock::parseAssetIds((string) ($props['assetIds'] ?? ''));
            $items = [];
            foreach ($ids as $assetId) {
                $item = [
                    'src' => $basePath . '/media/' . $assetId,
                    'srcWebp' => '',
                    'alt' => '',
                    'focusX' => '50',
                    'focusY' => '50',
                ];
                try {
                    $asset = $this->media->findById(new MediaId($assetId), $siteId);
                } catch (\InvalidArgumentException) {
                    $asset = null;
                }
                if ($asset !== null) {
                    if ($this->media->hasVariant($asset->id, 'webp')) {
                        $item['srcWebp'] = $basePath . '/media/' . $assetId . '/webp';
                    }
                    $item['alt'] = $asset->displayAlt();
                    $item['focusX'] = (string) $asset->focusX;
                    $item['focusY'] = (string) $asset->focusY;
                }
                $items[] = $item;
            }
            $props['items'] = $items;
            $node['props'] = $props;
        }
        $children = $node['children'] ?? null;
        if (is_array($children)) {
            $node['children'] = array_map(
                function (mixed $child) use ($basePath, $siteId): mixed {
                    return is_array($child) ? $this->resolveMediaUrls($child, $basePath, $siteId) : $child;
                },
                $children,
            );
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractSearchText(array $payload): string
    {
        $parts = [];
        $walk = function (array $node) use (&$walk, &$parts): void {
            $props = $node['props'] ?? [];
            if (is_array($props)) {
                foreach (['text', 'label', 'alt', 'caption', 'title', 'csv'] as $key) {
                    if (isset($props[$key]) && is_string($props[$key]) && $props[$key] !== '') {
                        $parts[] = $props[$key];
                    }
                }
            }
            $children = $node['children'] ?? [];
            if (is_array($children)) {
                foreach ($children as $child) {
                    if (is_array($child)) {
                        $walk($child);
                    }
                }
            }
        };
        $root = $payload['root'] ?? null;
        if (is_array($root)) {
            $walk($root);
        }

        return trim(implode(' ', $parts));
    }
}
