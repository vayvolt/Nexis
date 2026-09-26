<?php

declare(strict_types=1);

namespace Nexis\Site;

use Nexis\Builder\SnapshotRepository;
use Nexis\Content\PageRepository;
use Nexis\Content\PageStatus;
use Nexis\Media\MediaRepository;
use Nexis\Support\Clock;
use Nexis\Theme\ThemeCatalog;
use PDO;
use RuntimeException;
use ZipArchive;

final class SiteExporter
{
    public function __construct(
        private SiteRepository $sites,
        private PageRepository $pages,
        private MediaRepository $media,
        private SnapshotRepository $snapshots,
        private ThemeCatalog $themes,
        private PdoSiteSettingsRepository $settings,
        private PDO $pdo,
        private Clock $clock,
        private string $mediaPath,
        private string $exportPath,
    ) {
    }

    public function exportZip(): string
    {
        $site = $this->sites->installed();
        if ($site === null) {
            throw new RuntimeException('Keine Website zum Export.');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ext-zip fehlt für Site-Export.');
        }
        if (!is_dir($this->exportPath) && !mkdir($this->exportPath, 0775, true) && !is_dir($this->exportPath)) {
            throw new RuntimeException('Export-Verzeichnis fehlt.');
        }

        $pages = [];
        $locales = array_map(static fn (SiteLocale $l): string => $l->locale, $site->locales);
        if ($locales === []) {
            $locales = [$site->defaultLocale];
        }
        foreach (array_unique($locales) as $locale) {
            foreach ($this->pages->listBySite($site->id, $locale) as $page) {
                $document = null;
                if ($page->publishedSnapshotId !== null) {
                    $snapshot = $this->snapshots->findById($page->publishedSnapshotId);
                    if ($snapshot !== null) {
                        $document = $snapshot->payload;
                    }
                }
                $pages[$page->id->value] = [
                    'id' => $page->id->value,
                    'locale' => $page->locale,
                    'type' => $page->type,
                    'slug' => $page->slug,
                    'path' => $page->path,
                    'title' => $page->title,
                    'status' => $page->status->value,
                    'body_text' => $page->bodyText,
                    'translation_group_id' => $page->translationGroupId,
                    'search_text' => $page->searchText,
                    'meta_title' => $page->metaTitle,
                    'meta_description' => $page->metaDescription,
                    'robots' => $page->robots,
                    'published' => $page->status === PageStatus::Published,
                    'document' => $document,
                ];
            }
        }

        $mediaItems = [];
        foreach ($this->media->listBySite($site->id) as $asset) {
            $mediaItems[] = [
                'id' => $asset->id->value,
                'filename' => $asset->originalName,
                'alt_text' => $asset->altText,
                'mime' => $asset->mime,
                'extension' => $asset->extension,
                'disk_key' => $asset->diskKey,
                'width' => $asset->width,
                'height' => $asset->height,
                'checksum' => $asset->checksum,
                'byte_size' => $asset->byteSize,
            ];
        }

        $payload = [
            'format' => 'nexis.site.export',
            'version' => 2,
            'exported_at' => $this->clock->now()->format(DATE_ATOM),
            'site' => [
                'id' => $site->id->value,
                'name' => $site->name,
                'primary_domain' => $site->primaryDomain,
                'default_locale' => $site->defaultLocale,
                'locale_url_strategy' => $site->localeUrlStrategy->value,
                'locales' => array_map(static fn (SiteLocale $l): array => [
                    'locale' => $l->locale,
                    'label' => $l->label,
                    'url_prefix' => $l->urlPrefix,
                    'hreflang' => $l->hreflang,
                    'is_default' => $l->isDefault,
                    'enabled' => $l->enabled,
                ], $site->locales),
                'domains' => $this->exportDomains($site->id),
            ],
            'theme' => [
                'key' => $this->themes->themeKeyForSite($site->id),
                'tokens' => $this->themes->overrides($site->id),
                'custom_css' => $this->themes->customCss($site->id),
            ],
            'settings' => $this->exportSettings($site->id),
            'menus' => $this->exportMenus($site->id),
            'redirects' => $this->exportRedirects($site->id),
            'pages' => array_values($pages),
            'media' => $mediaItems,
        ];

        $stamp = gmdate('Ymd-His');
        $zipPath = $this->exportPath . DIRECTORY_SEPARATOR . 'site-export-' . $stamp . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP konnte nicht angelegt werden.');
        }
        $zip->addFromString('site.json', json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        foreach ($mediaItems as $item) {
            $src = $this->mediaPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $item['disk_key']);
            if (is_file($src)) {
                $zip->addFile($src, 'media/' . basename((string) $item['disk_key']));
            }
        }
        $zip->close();

        return $zipPath;
    }

    /**
     * @return list<array{host: string, locale: ?string, is_primary: bool}>
     */
    private function exportDomains(SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT host, locale, is_primary FROM site_domains WHERE site_id = :site_id ORDER BY is_primary DESC, host ASC',
        );
        $stmt->execute(['site_id' => $siteId->value]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'host' => (string) $row['host'],
                'locale' => is_string($row['locale'] ?? null) ? (string) $row['locale'] : null,
                'is_primary' => (int) ($row['is_primary'] ?? 0) === 1,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function exportSettings(SiteId $siteId): array
    {
        return $this->settings->all($siteId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportMenus(SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, handle, name, locale FROM menus WHERE site_id = :site_id ORDER BY handle ASC, locale ASC',
        );
        $stmt->execute(['site_id' => $siteId->value]);
        $menus = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $menuId = (string) $row['id'];
            $itemStmt = $this->pdo->prepare(
                'SELECT id, parent_id, page_id, label, url, sort_order
                 FROM menu_items WHERE menu_id = :menu_id ORDER BY sort_order ASC',
            );
            $itemStmt->execute(['menu_id' => $menuId]);
            $items = [];
            foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $items[] = [
                    'id' => (string) $item['id'],
                    'parent_id' => is_string($item['parent_id'] ?? null) ? (string) $item['parent_id'] : null,
                    'page_id' => is_string($item['page_id'] ?? null) ? (string) $item['page_id'] : null,
                    'label' => (string) $item['label'],
                    'url' => is_string($item['url'] ?? null) ? (string) $item['url'] : null,
                    'sort_order' => (int) $item['sort_order'],
                ];
            }
            $menus[] = [
                'handle' => (string) $row['handle'],
                'name' => (string) $row['name'],
                'locale' => (string) $row['locale'],
                'items' => $items,
            ];
        }

        return $menus;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportRedirects(SiteId $siteId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT locale, from_path, to_url, status_code
                 FROM plugin_nexis_redirects WHERE site_id = :site_id ORDER BY from_path ASC',
            );
            $stmt->execute(['site_id' => $siteId->value]);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'locale' => is_string($row['locale'] ?? null) ? (string) $row['locale'] : null,
                'from_path' => (string) $row['from_path'],
                'to_url' => (string) $row['to_url'],
                'status_code' => (int) $row['status_code'],
            ];
        }

        return $out;
    }
}
