<?php

declare(strict_types=1);

namespace Nexis\Content;

use DateTimeImmutable;
use Nexis\Auth\UserId;
use Nexis\Builder\RevisionId;
use Nexis\Builder\SnapshotId;
use Nexis\Site\SiteId;
use Nexis\Support\Clock;
use PDO;

final class PdoPageRepository implements PageRepository
{
    public function __construct(
        private PDO $pdo,
        private Clock $clock,
    ) {
    }

    public function findById(PageId $id): ?Page
    {
        $stmt = $this->pdo->prepare(
            $this->selectWithPublishMeta()
            . ' WHERE p.id = :id AND p.deleted_at IS NULL LIMIT 1',
        );
        $stmt->execute(['id' => $id->value]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function findByPath(SiteId $siteId, string $locale, string $path): ?Page
    {
        $stmt = $this->pdo->prepare(
            $this->selectWithPublishMeta()
            . ' WHERE p.site_id = :site_id AND p.locale = :locale AND p.path = :path AND p.deleted_at IS NULL
             LIMIT 1',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'locale' => $locale,
            'path' => $path,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function listBySite(SiteId $siteId, string $locale): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM pages
             WHERE site_id = :site_id AND locale = :locale AND deleted_at IS NULL
             ORDER BY path ASC',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'locale' => $locale,
        ]);
        $pages = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $pages[] = $this->map($row);
            }
        }

        return $pages;
    }

    public function listBySiteAndType(SiteId $siteId, string $locale, string $type): array
    {
        $orderBy = $type === PageType::PAGE
            ? 'title ASC, path ASC'
            : 'updated_at DESC, path ASC';
        $stmt = $this->pdo->prepare(
            'SELECT * FROM pages
             WHERE site_id = :site_id AND locale = :locale AND type = :type AND deleted_at IS NULL
             ORDER BY ' . $orderBy,
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'locale' => $locale,
            'type' => $type,
        ]);
        $pages = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $pages[] = $this->map($row);
            }
        }

        return $pages;
    }

    public function listAllLocalesByType(SiteId $siteId, string $type): array
    {
        $orderBy = $type === PageType::PAGE
            ? 'path ASC, locale ASC, title ASC'
            : 'updated_at DESC, locale ASC, path ASC';
        $stmt = $this->pdo->prepare(
            'SELECT * FROM pages
             WHERE site_id = :site_id AND type = :type AND deleted_at IS NULL
             ORDER BY ' . $orderBy,
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'type' => $type,
        ]);
        $pages = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $pages[] = $this->map($row);
            }
        }

        return $pages;
    }

    public function listGroupedByType(SiteId $siteId, string $type, string $preferredLocale, string $defaultLocale): array
    {
        $all = $this->listAllLocalesByType($siteId, $type);
        /** @var array<string, list<Page>> $groups */
        $groups = [];
        foreach ($all as $page) {
            $groups[$page->translationGroupId][] = $page;
        }
        $out = [];
        foreach ($groups as $groupId => $siblings) {
            $byLocale = [];
            foreach ($siblings as $sibling) {
                $byLocale[$sibling->locale] = $sibling;
            }
            $primary = $byLocale[$preferredLocale]
                ?? $byLocale[$defaultLocale]
                ?? $siblings[0];
            $out[] = [
                'groupId' => $groupId,
                'primary' => $primary,
                'byLocale' => $byLocale,
            ];
        }
        usort(
            $out,
            static fn (array $a, array $b): int => strcmp($a['primary']->path, $b['primary']->path),
        );

        return $out;
    }

    public function alternates(Page $page): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM pages
             WHERE site_id = :site_id AND translation_group_id = :group_id AND deleted_at IS NULL
             ORDER BY locale ASC',
        );
        $stmt->execute([
            'site_id' => $page->siteId->value,
            'group_id' => $page->translationGroupId,
        ]);
        $pages = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $pages[] = $this->map($row);
            }
        }

        return $pages;
    }

    public function listPublished(SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM pages
             WHERE site_id = :site_id AND status = :status AND deleted_at IS NULL
               AND published_snapshot_id IS NOT NULL
             ORDER BY locale ASC, path ASC',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'status' => PageStatus::Published->value,
        ]);
        $pages = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $pages[] = $this->map($row);
            }
        }

        return $pages;
    }

    public function listPublishedForLocale(SiteId $siteId, string $locale): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM pages
             WHERE site_id = :site_id AND locale = :locale AND status = :status AND deleted_at IS NULL
               AND published_snapshot_id IS NOT NULL
             ORDER BY path ASC',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'locale' => $locale,
            'status' => PageStatus::Published->value,
        ]);
        $pages = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $pages[] = $this->map($row);
            }
        }

        return $pages;
    }

    public function listPublishedByTypeForLocale(SiteId $siteId, string $locale, string $type): array
    {
        $stmt = $this->pdo->prepare(
            $this->selectWithPublishMeta()
            . ' WHERE p.site_id = :site_id AND p.locale = :locale AND p.type = :type AND p.status = :status
               AND p.deleted_at IS NULL AND p.published_snapshot_id IS NOT NULL
             ORDER BY COALESCE(s.published_at, p.updated_at) DESC, p.path ASC',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'locale' => $locale,
            'type' => $type,
            'status' => PageStatus::Published->value,
        ]);
        $pages = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $pages[] = $this->map($row);
            }
        }

        return $pages;
    }

    public function listDueScheduled(DateTimeImmutable $now, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM pages
             WHERE deleted_at IS NULL
               AND scheduled_at IS NOT NULL AND scheduled_at <= :now
               AND status IN (:scheduled, :published)
             ORDER BY scheduled_at ASC
             LIMIT ' . max(1, $limit),
        );
        $stmt->execute([
            'scheduled' => PageStatus::Scheduled->value,
            'published' => PageStatus::Published->value,
            'now' => $now->format('Y-m-d H:i:s.v'),
        ]);
        $pages = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $pages[] = $this->map($row);
            }
        }

        return $pages;
    }

    public function listDueUnpublish(DateTimeImmutable $now, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            $this->selectWithPublishMeta() . '
             WHERE p.deleted_at IS NULL
               AND p.published_snapshot_id IS NOT NULL
               AND p.unpublish_at IS NOT NULL AND p.unpublish_at <= :now
             ORDER BY p.unpublish_at ASC
             LIMIT ' . max(1, $limit),
        );
        $stmt->execute([
            'now' => $now->format('Y-m-d H:i:s.v'),
        ]);
        $pages = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $pages[] = $this->map($row);
            }
        }

        return $pages;
    }

    public function save(Page $page): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s.v');
        $stmt = $this->pdo->prepare(
            'INSERT INTO pages (
                id, site_id, parent_id, translation_group_id, type, slug, path, locale, title, body_text, status,
                scheduled_at, scheduled_by, unpublish_at, unpublish_by,
                published_revision_id, published_snapshot_id, search_text, meta_title, meta_description, focus_keyword, robots,
                created_at, updated_at
            ) VALUES (
                :id, :site_id, NULL, :translation_group_id, :type, :slug, :path, :locale, :title, :body_text, :status,
                :scheduled_at, :scheduled_by, :unpublish_at, :unpublish_by,
                :published_revision_id, :published_snapshot_id, :search_text, :meta_title, :meta_description, :focus_keyword, :robots,
                :created_at, :updated_at
            )
            ON DUPLICATE KEY UPDATE
                type = VALUES(type),
                slug = VALUES(slug),
                path = VALUES(path),
                title = VALUES(title),
                body_text = VALUES(body_text),
                status = VALUES(status),
                scheduled_at = VALUES(scheduled_at),
                scheduled_by = VALUES(scheduled_by),
                unpublish_at = VALUES(unpublish_at),
                unpublish_by = VALUES(unpublish_by),
                published_revision_id = VALUES(published_revision_id),
                published_snapshot_id = VALUES(published_snapshot_id),
                search_text = VALUES(search_text),
                meta_title = VALUES(meta_title),
                meta_description = VALUES(meta_description),
                focus_keyword = VALUES(focus_keyword),
                robots = VALUES(robots),
                updated_at = VALUES(updated_at)',
        );
        $stmt->execute([
            'id' => $page->id->value,
            'site_id' => $page->siteId->value,
            'translation_group_id' => $page->translationGroupId,
            'type' => $page->type !== '' ? $page->type : PageType::PAGE,
            'slug' => $page->slug,
            'path' => $page->path,
            'locale' => $page->locale,
            'title' => $page->title,
            'body_text' => $page->bodyText,
            'status' => $page->status->value,
            'scheduled_at' => $page->scheduledAt?->format('Y-m-d H:i:s.v'),
            'scheduled_by' => $page->scheduledBy?->value,
            'unpublish_at' => $page->unpublishAt?->format('Y-m-d H:i:s.v'),
            'unpublish_by' => $page->unpublishBy?->value,
            'published_revision_id' => $page->publishedRevisionId?->value,
            'published_snapshot_id' => $page->publishedSnapshotId?->value,
            'search_text' => $page->searchText,
            'meta_title' => $page->metaTitle,
            'meta_description' => $page->metaDescription,
            'focus_keyword' => $page->focusKeyword,
            'robots' => $page->robots,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function softDelete(PageId $id): bool
    {
        $trashPath = '/.deleted/' . $id->value;
        $trashSlug = 'deleted-' . substr(str_replace('-', '', $id->value), 0, 12);
        $stmt = $this->pdo->prepare(
            'UPDATE pages
             SET deleted_at = :deleted_at,
                 status = :status,
                 path = :path,
                 slug = :slug,
                 scheduled_at = NULL,
                 scheduled_by = NULL,
                 unpublish_at = NULL,
                 unpublish_by = NULL,
                 published_revision_id = NULL,
                 published_snapshot_id = NULL,
                 updated_at = :updated_at
             WHERE id = :id AND deleted_at IS NULL',
        );
        $now = $this->clock->now()->format('Y-m-d H:i:s.v');
        $stmt->execute([
            'deleted_at' => $now,
            'status' => PageStatus::Draft->value,
            'path' => $trashPath,
            'slug' => $trashSlug,
            'updated_at' => $now,
            'id' => $id->value,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function softDeleteTranslationGroup(Page $page): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM pages
             WHERE site_id = :site_id
               AND translation_group_id = :group_id
               AND deleted_at IS NULL',
        );
        $stmt->execute([
            'site_id' => $page->siteId->value,
            'group_id' => $page->translationGroupId,
        ]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $count = 0;
        foreach ($ids as $idValue) {
            if (!is_string($idValue) || $idValue === '') {
                continue;
            }
            if ($this->softDelete(new PageId($idValue))) {
                $count++;
            }
        }

        return $count;
    }

    public function pathExists(SiteId $siteId, string $locale, string $path): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM pages
             WHERE site_id = :site_id AND locale = :locale AND path = :path
             LIMIT 1',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'locale' => $locale,
            'path' => $path,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Free unique paths held by already soft-deleted rows (legacy).
     */
    public function relocateSoftDeletedPaths(): int
    {
        $stmt = $this->pdo->query(
            "SELECT id, path FROM pages
             WHERE deleted_at IS NOT NULL
               AND path NOT LIKE '/.deleted/%'",
        );
        if ($stmt === false) {
            return 0;
        }
        $count = 0;
        $update = $this->pdo->prepare(
            'UPDATE pages SET path = :path, slug = :slug, updated_at = :updated_at WHERE id = :id',
        );
        $now = $this->clock->now()->format('Y-m-d H:i:s.v');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $update->execute([
                'path' => '/.deleted/' . $id,
                'slug' => 'deleted-' . substr(str_replace('-', '', $id), 0, 12),
                'updated_at' => $now,
                'id' => $id,
            ]);
            $count += $update->rowCount();
        }

        return $count;
    }

    public function countBySite(SiteId $siteId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM pages WHERE site_id = :site_id AND deleted_at IS NULL',
        );
        $stmt->execute(['site_id' => $siteId->value]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): Page
    {
        $body = $row['body_text'] ?? null;
        $rev = $row['published_revision_id'] ?? null;
        $snap = $row['published_snapshot_id'] ?? null;
        $search = $row['search_text'] ?? null;
        $metaTitle = $row['meta_title'] ?? null;
        $metaDescription = $row['meta_description'] ?? null;
        $focusKeyword = $row['focus_keyword'] ?? null;
        $robots = $row['robots'] ?? null;
        $scheduledRaw = $row['scheduled_at'] ?? null;
        $scheduledByRaw = $row['scheduled_by'] ?? null;
        $scheduledAt = null;
        if (is_string($scheduledRaw) && $scheduledRaw !== '') {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.v', $scheduledRaw)
                ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $scheduledRaw);
            $scheduledAt = $parsed instanceof DateTimeImmutable ? $parsed : null;
        }

        $type = $row['type'] ?? PageType::PAGE;
        $updatedRaw = $row['updated_at'] ?? null;
        $updatedAt = null;
        if (is_string($updatedRaw) && $updatedRaw !== '') {
            $parsedUpdated = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.v', $updatedRaw)
                ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $updatedRaw);
            $updatedAt = $parsedUpdated instanceof DateTimeImmutable ? $parsedUpdated : null;
        }

        $publishedRaw = $row['snapshot_published_at'] ?? null;
        $publishedAt = null;
        if (is_string($publishedRaw) && $publishedRaw !== '') {
            $parsedPublished = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.v', $publishedRaw)
                ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $publishedRaw);
            $publishedAt = $parsedPublished instanceof DateTimeImmutable ? $parsedPublished : null;
        }

        $authorRaw = $row['author_display_name'] ?? null;
        $authorName = is_string($authorRaw) && trim($authorRaw) !== '' ? trim($authorRaw) : null;

        $unpublishRaw = $row['unpublish_at'] ?? null;
        $unpublishAt = null;
        if (is_string($unpublishRaw) && $unpublishRaw !== '') {
            $parsedUnpublish = DateTimeImmutable::createFromFormat('Y-m-d H:i:s.v', $unpublishRaw)
                ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $unpublishRaw);
            $unpublishAt = $parsedUnpublish instanceof DateTimeImmutable ? $parsedUnpublish : null;
        }
        $unpublishByRaw = $row['unpublish_by'] ?? null;

        return new Page(
            new PageId((string) $row['id']),
            new SiteId((string) $row['site_id']),
            (string) $row['translation_group_id'],
            (string) $row['locale'],
            (string) $row['slug'],
            (string) $row['path'],
            (string) $row['title'],
            is_string($body) ? $body : null,
            PageStatus::from((string) $row['status']),
            is_string($rev) && $rev !== '' ? new RevisionId($rev) : null,
            is_string($snap) && $snap !== '' ? new SnapshotId($snap) : null,
            is_string($search) ? $search : null,
            is_string($metaTitle) ? $metaTitle : null,
            is_string($metaDescription) ? $metaDescription : null,
            is_string($robots) && $robots !== '' ? $robots : 'index,follow',
            $scheduledAt,
            is_string($scheduledByRaw) && $scheduledByRaw !== '' ? new UserId($scheduledByRaw) : null,
            is_string($type) && $type !== '' ? $type : PageType::PAGE,
            $updatedAt,
            $publishedAt,
            $authorName,
            $unpublishAt,
            is_string($unpublishByRaw) && $unpublishByRaw !== '' ? new UserId($unpublishByRaw) : null,
            is_string($focusKeyword) && trim($focusKeyword) !== '' ? trim($focusKeyword) : null,
        );
    }

    private function selectWithPublishMeta(): string
    {
        return 'SELECT p.*,
                s.published_at AS snapshot_published_at,
                u.display_name AS author_display_name
             FROM pages p
             LEFT JOIN page_snapshots s ON s.id = p.published_snapshot_id
             LEFT JOIN users u ON u.id = s.published_by';
    }
}
