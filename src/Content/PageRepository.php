<?php

declare(strict_types=1);

namespace Nexis\Content;

use DateTimeImmutable;
use Nexis\Site\SiteId;

interface PageRepository
{
    public function findById(PageId $id): ?Page;

    public function findByPath(SiteId $siteId, string $locale, string $path): ?Page;

    /**
     * @return list<Page>
     */
    public function listBySite(SiteId $siteId, string $locale): array;

    /**
     * @return list<Page>
     */
    public function listBySiteAndType(SiteId $siteId, string $locale, string $type): array;

    /**
     * All locales for a type (for admin translation-group lists).
     *
     * @return list<Page>
     */
    public function listAllLocalesByType(SiteId $siteId, string $type): array;

    /**
     * One admin row per translation group.
     *
     * @return list<array{groupId: string, primary: Page, byLocale: array<string, Page>}>
     */
    public function listGroupedByType(SiteId $siteId, string $type, string $preferredLocale, string $defaultLocale): array;

    /**
     * @return list<Page>
     */
    public function alternates(Page $page): array;

    /**
     * @return list<Page>
     */
    public function listPublished(SiteId $siteId): array;

    /**
     * @return list<Page>
     */
    public function listPublishedForLocale(SiteId $siteId, string $locale): array;

    /**
     * @return list<Page>
     */
    public function listPublishedByTypeForLocale(SiteId $siteId, string $locale, string $type): array;

    /**
     * @return list<Page>
     */
    public function listDueScheduled(DateTimeImmutable $now, int $limit = 20): array;

    /**
     * Published pages whose unpublish_at is due.
     *
     * @return list<Page>
     */
    public function listDueUnpublish(DateTimeImmutable $now, int $limit = 20): array;

    public function save(Page $page): void;

    public function softDelete(PageId $id): bool;

    /**
     * Soft-delete every locale in the page's translation group (same site).
     * Paths are relocated so the unique (site, locale, path) key is freed.
     *
     * @return int Number of pages deleted
     */
    public function softDeleteTranslationGroup(Page $page): int;

    /**
     * Whether a path is already used (including soft-deleted rows).
     */
    public function pathExists(SiteId $siteId, string $locale, string $path): bool;

    /**
     * Free unique paths held by already soft-deleted rows (legacy data).
     */
    public function relocateSoftDeletedPaths(): int;

    public function countBySite(SiteId $siteId): int;
}
