<?php

declare(strict_types=1);

namespace Nexis\Site;

use Nexis\Auth\UserId;
use Nexis\Builder\DocumentService;
use Nexis\Builder\PublishService;
use Nexis\Builder\RevisionRepository;
use Nexis\Cache\PageCache;
use Nexis\Content\MenuRepository;
use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageRepository;
use Nexis\Content\PageStatus;
use Nexis\Content\PageType;
use Nexis\Media\MediaId;
use Nexis\Media\MediaLibrary;
use Nexis\Media\MediaRepository;
use Nexis\Support\Uuid;
use Nexis\Theme\ThemeCatalog;
use PDO;
use RuntimeException;
use ZipArchive;

final class SiteImporter
{
    public function __construct(
        private SiteRepository $sites,
        private PageRepository $pages,
        private MediaRepository $media,
        private DocumentService $documents,
        private RevisionRepository $revisions,
        private PublishService $publish,
        private PageCache $cache,
        private ThemeCatalog $themes,
        private MenuRepository $menus,
        private PdoSiteSettingsRepository $settings,
        private MediaLibrary $library,
        private PDO $pdo,
    ) {
    }

    /**
     * @return array{pages: int, media: int, published: int, menus: int, redirects: int, settings: int}
     */
    public function importZip(string $zipPath, UserId $actor, string $basePath = ''): array
    {
        $site = $this->sites->installed();
        if ($site === null) {
            throw new RuntimeException('Keine Website für den Import.');
        }
        $payload = SiteExportArchive::read($zipPath);
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('ZIP konnte nicht geöffnet werden.');
        }

        $this->importSiteMeta($site, $payload['site'] ?? null);
        $settingsCount = $this->importSettings($site->id, $payload['settings'] ?? null);
        $this->importTheme($site->id, $payload['theme'] ?? null, $actor);

        $mediaCount = 0;
        $mediaList = $payload['media'] ?? [];
        if (is_array($mediaList)) {
            foreach ($mediaList as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if ($this->importMedia($site->id, $item, $zip)) {
                    $mediaCount++;
                }
            }
        }

        $pageCount = 0;
        $published = 0;
        $pageList = $payload['pages'] ?? [];
        if (is_array($pageList)) {
            foreach ($pageList as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $result = $this->importPage($site->id, $item, $actor, $basePath);
                if ($result['saved']) {
                    $pageCount++;
                }
                if ($result['published']) {
                    $published++;
                }
            }
        }

        $menusCount = $this->importMenus($site->id, $payload['menus'] ?? null);
        $redirectsCount = $this->importRedirects($site->id, $payload['redirects'] ?? null);

        $zip->close();
        $this->cache->invalidateSite($site->id);

        return [
            'pages' => $pageCount,
            'media' => $mediaCount,
            'published' => $published,
            'menus' => $menusCount,
            'redirects' => $redirectsCount,
            'settings' => $settingsCount,
        ];
    }

    private function importSiteMeta(Site $site, mixed $raw): void
    {
        if (!is_array($raw)) {
            return;
        }
        $name = trim((string) ($raw['name'] ?? $site->name));
        $domain = trim((string) ($raw['primary_domain'] ?? $site->primaryDomain));
        $defaultLocale = (string) ($raw['default_locale'] ?? $site->defaultLocale);
        $strategy = LocaleUrlStrategy::tryFrom((string) ($raw['locale_url_strategy'] ?? ''))
            ?? $site->localeUrlStrategy;

        $locales = $raw['locales'] ?? null;
        if (is_array($locales)) {
            foreach ($locales as $loc) {
                if (!is_array($loc)) {
                    continue;
                }
                $code = strtolower(trim((string) ($loc['locale'] ?? '')));
                if ($code === '') {
                    continue;
                }
                $exists = array_find($site->locales, static fn (SiteLocale $l): bool => $l->locale === $code);
                $label = trim((string) ($loc['label'] ?? $code));
                $prefix = isset($loc['url_prefix']) && is_string($loc['url_prefix']) ? $loc['url_prefix'] : explode('-', $code)[0];
                $hreflang = trim((string) ($loc['hreflang'] ?? $code));
                $enabled = ($loc['enabled'] ?? true) !== false && ($loc['enabled'] ?? true) !== 0 && ($loc['enabled'] ?? true) !== '0';
                if ($exists === null) {
                    $this->sites->addLocale($site->id, $code, $label !== '' ? $label : $code, $prefix, $hreflang !== '' ? $hreflang : $code, $enabled);
                } else {
                    $this->sites->updateLocale($site->id, $code, $label !== '' ? $label : $exists->label, $prefix, $hreflang !== '' ? $hreflang : $exists->hreflang, $enabled);
                }
            }
            $site = $this->sites->findById($site->id) ?? $site;
        }

        if ($site->locale($defaultLocale) === null) {
            $defaultLocale = $site->defaultLocale;
        }
        $this->sites->updateBasics($site->id, $name !== '' ? $name : $site->name, $domain !== '' ? $domain : $site->primaryDomain, $defaultLocale, $strategy);

        $domains = $raw['domains'] ?? null;
        if (is_array($domains)) {
            $map = [];
            foreach ($domains as $domainRow) {
                if (!is_array($domainRow)) {
                    continue;
                }
                $host = strtolower(trim((string) ($domainRow['host'] ?? '')));
                $locale = is_string($domainRow['locale'] ?? null) ? trim((string) $domainRow['locale']) : '';
                if ($host === '' || $locale === '') {
                    continue;
                }
                $map[$locale] = $host;
            }
            if ($map !== []) {
                $this->sites->replaceLocaleDomains($site->id, $map);
            }
        }
    }

    private function importTheme(SiteId $siteId, mixed $raw, UserId $actor): void
    {
        if (!is_array($raw)) {
            return;
        }
        $key = (string) ($raw['key'] ?? '');
        if ($key !== '') {
            $this->themes->assignTheme($siteId, $key);
        }
        $tokens = $raw['tokens'] ?? [];
        $css = $raw['custom_css'] ?? null;
        if (!is_array($tokens)) {
            $tokens = [];
        }
        $normalized = [];
        foreach ($tokens as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $normalized[$k] = $v;
            }
        }
        $this->themes->saveOverrides(
            $siteId,
            $normalized,
            is_string($css) && $css !== '' ? $css : null,
            $actor->value,
        );
    }

    private function importSettings(SiteId $siteId, mixed $raw): int
    {
        if (!is_array($raw)) {
            return 0;
        }
        $count = 0;
        foreach ($raw as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $this->settings->set($siteId, $key, $value);
            $count++;
        }

        return $count;
    }

    private function importMenus(SiteId $siteId, mixed $raw): int
    {
        if (!is_array($raw)) {
            return 0;
        }
        $count = 0;
        foreach ($raw as $menu) {
            if (!is_array($menu)) {
                continue;
            }
            $handle = (string) ($menu['handle'] ?? '');
            $locale = (string) ($menu['locale'] ?? '');
            $name = (string) ($menu['name'] ?? $handle);
            if ($handle === '' || $locale === '') {
                continue;
            }
            $entity = $this->menus->ensure($siteId, $handle, $name !== '' ? $name : $handle, $locale);
            $itemsRaw = $menu['items'] ?? [];
            if (!is_array($itemsRaw)) {
                $itemsRaw = [];
            }
            /** @var array<string, int> $idToIndex */
            $idToIndex = [];
            /** @var list<array{label: string, page_id: ?string, url: ?string, parent: ?string, parent_ref: ?string}> $draft */
            $draft = [];
            foreach ($itemsRaw as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $label = trim((string) ($item['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $id = (string) ($item['id'] ?? '');
                if ($id !== '') {
                    $idToIndex[$id] = count($draft);
                }
                $draft[] = [
                    'label' => $label,
                    'page_id' => is_string($item['page_id'] ?? null) && $item['page_id'] !== '' ? (string) $item['page_id'] : null,
                    'url' => is_string($item['url'] ?? null) && $item['url'] !== '' ? (string) $item['url'] : null,
                    'parent' => null,
                    'parent_ref' => is_string($item['parent_id'] ?? null) ? (string) $item['parent_id'] : null,
                ];
            }
            $normalized = [];
            foreach ($draft as $item) {
                $parent = null;
                $parentRef = $item['parent_ref'];
                if (is_string($parentRef) && isset($idToIndex[$parentRef])) {
                    $parent = (string) $idToIndex[$parentRef];
                }
                $normalized[] = [
                    'label' => $item['label'],
                    'page_id' => $item['page_id'],
                    'url' => $item['url'],
                    'parent' => $parent,
                ];
            }
            $this->menus->replaceItems($entity->id, $normalized);
            $count++;
        }

        return $count;
    }

    private function importRedirects(SiteId $siteId, mixed $raw): int
    {
        if (!is_array($raw)) {
            return 0;
        }
        $count = 0;
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $from = (string) ($row['from_path'] ?? '');
            $to = (string) ($row['to_url'] ?? '');
            if ($from === '' || $to === '') {
                continue;
            }
            $locale = is_string($row['locale'] ?? null) && $row['locale'] !== '' ? (string) $row['locale'] : null;
            $code = (int) ($row['status_code'] ?? 301);
            if (!in_array($code, [301, 302, 307, 308], true)) {
                $code = 301;
            }
            try {
                $del = $this->pdo->prepare(
                    'DELETE FROM plugin_nexis_redirects
                     WHERE site_id = :site_id AND from_path = :from_path
                       AND ((locale IS NULL AND :locale_null = 1) OR locale = :locale)',
                );
                $del->execute([
                    'site_id' => $siteId->value,
                    'from_path' => $from,
                    'locale_null' => $locale === null ? 1 : 0,
                    'locale' => $locale,
                ]);
                $stmt = $this->pdo->prepare(
                    'INSERT INTO plugin_nexis_redirects (id, site_id, locale, from_path, to_url, status_code, created_at)
                     VALUES (:id, :site_id, :locale, :from_path, :to_url, :status_code, :created_at)',
                );
                $stmt->execute([
                    'id' => Uuid::v7(),
                    'site_id' => $siteId->value,
                    'locale' => $locale,
                    'from_path' => $from,
                    'to_url' => $to,
                    'status_code' => $code,
                    'created_at' => gmdate('Y-m-d H:i:s.v'),
                ]);
                $count++;
            } catch (\Throwable) {
                // Plugin table may be missing when redirects plugin is disabled.
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function importMedia(SiteId $siteId, array $item, ZipArchive $zip): bool
    {
        $idRaw = (string) ($item['id'] ?? '');
        $filename = (string) ($item['filename'] ?? 'file');
        $diskKey = (string) ($item['disk_key'] ?? '');
        if ($idRaw === '' || $diskKey === '') {
            return false;
        }
        try {
            $id = new MediaId($idRaw);
        } catch (\InvalidArgumentException) {
            return false;
        }
        if ($this->media->findById($id, $siteId) !== null) {
            return false;
        }

        $entry = 'media/' . basename($diskKey);
        $binary = $zip->getFromName($entry);
        if (!is_string($binary) || $binary === '') {
            return false;
        }

        $alt = is_string($item['alt_text'] ?? null) ? (string) $item['alt_text'] : '';
        try {
            $this->library->importBinary($siteId, $id, $binary, $filename, $alt);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $item
     * @return array{saved: bool, published: bool}
     */
    private function importPage(SiteId $siteId, array $item, UserId $actor, string $basePath): array
    {
        $locale = (string) ($item['locale'] ?? '');
        $path = (string) ($item['path'] ?? '');
        $title = (string) ($item['title'] ?? '');
        if ($locale === '' || $path === '' || $title === '') {
            return ['saved' => false, 'published' => false];
        }

        $existing = $this->pages->findByPath($siteId, $locale, $path);
        if ($existing !== null) {
            $id = $existing->id;
            $group = $existing->translationGroupId;
        } else {
            $id = $this->pageIdFrom((string) ($item['id'] ?? ''));
            $group = is_string($item['translation_group_id'] ?? null) && (string) $item['translation_group_id'] !== ''
                ? (string) $item['translation_group_id']
                : Uuid::v7();
        }
        $slug = (string) ($item['slug'] ?? '');
        if ($slug === '') {
            $slug = $path === '/' ? 'home' : ltrim($path, '/');
        }
        $statusRaw = (string) ($item['status'] ?? 'draft');
        $status = PageStatus::tryFrom($statusRaw) ?? PageStatus::Draft;
        $body = $item['body_text'] ?? null;
        $search = $item['search_text'] ?? null;
        $metaTitle = $item['meta_title'] ?? null;
        $metaDescription = $item['meta_description'] ?? null;

        $page = new Page(
            $id,
            $siteId,
            $group,
            $locale,
            $slug,
            $path,
            $title,
            is_string($body) ? $body : null,
            $status === PageStatus::Published ? PageStatus::Draft : $status,
            null,
            null,
            is_string($search) ? $search : null,
            is_string($metaTitle) ? $metaTitle : null,
            is_string($metaDescription) ? $metaDescription : null,
            is_string($item['robots'] ?? null) && $item['robots'] !== '' ? (string) $item['robots'] : 'index,follow',
            null,
            null,
            is_string($item['type'] ?? null) && $item['type'] !== '' ? (string) $item['type'] : PageType::PAGE,
        );
        $this->pages->save($page);

        $document = $item['document'] ?? null;
        $didPublish = false;
        if (is_array($document)) {
            /** @var array<string, mixed> $document */
            $latest = $this->revisions->latestForPage($page->id);
            $this->documents->save($page->id, $document, $latest?->documentHash, $actor, 'Import');
            if (!empty($item['published']) || $status === PageStatus::Published) {
                $fresh = $this->pages->findById($page->id);
                if ($fresh !== null) {
                    $this->publish->publish($fresh, null, $actor, $basePath);
                    $didPublish = true;
                }
            }
        }

        return ['saved' => true, 'published' => $didPublish];
    }

    private function pageIdFrom(string $raw): PageId
    {
        try {
            return new PageId($raw);
        } catch (\InvalidArgumentException) {
            return new PageId(Uuid::v7());
        }
    }
}
