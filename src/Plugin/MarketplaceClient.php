<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * HTTP client for the public Nexis plugin directory (docs/12).
 * Update checks POST install basics (version/PHP/DB/locale) like WordPress.org.
 */
final class MarketplaceClient
{
    /** Official public directory (no trailing slash). */
    public const DIRECTORY_URL = 'https://nexis.vayvolt.de';

    /** WordPress-style: at most one phone-home / update check per interval. */
    public const CHECK_TTL_SECONDS = 43200; // 12 hours

    /** Soft fail backoff so a dead directory does not block every admin page load. */
    public const CHECK_FAILURE_TTL_SECONDS = 900; // 15 minutes

    /** Directory allows ~12 update-checks/day/install — wait out the window after 429. */
    public const CHECK_RATE_LIMIT_TTL_SECONDS = 43200; // 12 hours

    /** Keep page-load paths non-blocking; background refresh uses the same budget. */
    public const CHECK_HTTP_TIMEOUT = 12;

    public const CHECK_CONNECT_TIMEOUT = 5;

    public function __construct(
        private string $baseUrl = self::DIRECTORY_URL,
        private ?LoggerInterface $logger = null,
        private string $coreVersion = '',
        private string $cacheDir = '',
    ) {
        $this->baseUrl = rtrim($baseUrl !== '' ? $baseUrl : self::DIRECTORY_URL, '/');
    }

    public function configured(): bool
    {
        return $this->baseUrl !== '';
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Invalidate cached update-check result (e.g. after installing from the directory).
     */
    public function clearUpdateCheckCache(): void
    {
        $path = $this->cacheFile();
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Read last update-check payload without touching the network.
     *
     * @return array{
     *   plugins: array<string, array{installed: string, latest: string, downloadUrl: string|null}>,
     *   themes: array<string, array{installed: string, latest: string, downloadUrl: string|null}>,
     *   cms: array{latest: string, phpRequirement: string, downloadUrl: string, updateAvailable: bool}|null,
     *   checkedAt: int|null,
     *   stale: bool,
     *   failed: bool
     * }
     */
    public function peekCachedUpdates(): array
    {
        $empty = [
            'plugins' => [],
            'themes' => [],
            'cms' => null,
            'checkedAt' => null,
            'stale' => true,
            'failed' => false,
        ];
        $cached = $this->readCache();
        if ($cached === null) {
            return $empty;
        }
        $now = time();
        $expiresAt = (int) ($cached['expiresAt'] ?? ((int) ($cached['checkedAt'] ?? 0) + self::CHECK_TTL_SECONDS));
        $payload = $this->normalizeUpdatePayload(
            is_array($cached['plugins'] ?? null) ? $cached['plugins'] : [],
            $cached['cms'] ?? null,
            is_array($cached['themes'] ?? null) ? $cached['themes'] : [],
        );

        return [
            'plugins' => $payload['plugins'],
            'themes' => $payload['themes'],
            'cms' => $payload['cms'],
            'checkedAt' => isset($cached['checkedAt']) ? (int) $cached['checkedAt'] : null,
            'stale' => $expiresAt <= $now,
            'failed' => !empty($cached['failed']),
        ];
    }

    /**
     * Whether a background refresh should run. Honors soft-fail / rate-limit TTL
     * regardless of fingerprint — callers used to pass incomplete plugin/theme maps
     * and burned the directory rate limit.
     *
     * @param array<string, string> $localVersions unused; kept for call-site compatibility
     * @param array{php?: string, db?: string, locale?: string, install_id?: string} $installInfo unused
     * @param array<string, string> $localThemes unused
     */
    public function needsNetworkUpdateCheck(
        array $localVersions = [],
        array $installInfo = [],
        array $localThemes = [],
    ): bool {
        unset($localVersions, $installInfo, $localThemes);
        if (!$this->configured()) {
            return false;
        }
        $cached = $this->readCache();
        if ($cached === null) {
            return true;
        }
        $expiresAt = (int) ($cached['expiresAt'] ?? ((int) ($cached['checkedAt'] ?? 0) + self::CHECK_TTL_SECONDS));

        return $expiresAt <= time();
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function search(string $q = '', int $page = 1, int $perPage = 20): array
    {
        $query = [
            'q' => $q,
            'page' => max(1, $page),
            'perPage' => max(1, min(50, $perPage)),
        ];
        if ($this->coreVersion !== '') {
            $query['compatible'] = $this->coreVersion;
        }
        /** @var array{items?: mixed, total?: mixed, page?: mixed, perPage?: mixed} $data */
        $data = $this->getJson('/api/v1/plugins', $query);

        return [
            'items' => isset($data['items']) && is_array($data['items']) ? array_values($data['items']) : [],
            'total' => (int) ($data['total'] ?? 0),
            'page' => (int) ($data['page'] ?? 1),
            'perPage' => (int) ($data['perPage'] ?? 20),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function searchThemes(string $q = '', int $page = 1, int $perPage = 20): array
    {
        $query = [
            'q' => $q,
            'page' => max(1, $page),
            'perPage' => max(1, min(50, $perPage)),
        ];
        if ($this->coreVersion !== '') {
            $query['compatible'] = $this->coreVersion;
        }
        /** @var array{items?: mixed, total?: mixed, page?: mixed, perPage?: mixed} $data */
        $data = $this->getJson('/api/v1/themes', $query);

        return [
            'items' => isset($data['items']) && is_array($data['items']) ? array_values($data['items']) : [],
            'total' => (int) ($data['total'] ?? 0),
            'page' => (int) ($data['page'] ?? 1),
            'perPage' => (int) ($data['perPage'] ?? 20),
        ];
    }

    /**
     * Published CMS packages from the directory (newest first).
     *
     * @return list<array{version: string, phpRequirement: string, downloadUrl: string, changelogMd: string}>
     */
    public function listCmsReleases(bool $allowNetwork = true): array
    {
        if (!$this->configured()) {
            return [];
        }
        $cached = $this->readCmsReleasesCache();
        if (!$allowNetwork) {
            return $cached ?? [];
        }
        try {
            $data = $this->getJson('/api/v1/cms/releases', [], self::CHECK_HTTP_TIMEOUT, self::CHECK_CONNECT_TIMEOUT);
        } catch (RuntimeException) {
            return $cached ?? [];
        }
        $raw = $data['items'] ?? null;
        if (!is_array($raw)) {
            return $cached ?? [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $version = trim((string) ($row['version'] ?? ''));
            $url = trim((string) ($row['downloadUrl'] ?? ''));
            if ($version === '' || $url === '') {
                continue;
            }
            $resolved = $this->resolveDownloadUrl($url);
            $out[] = [
                'version' => $version,
                'phpRequirement' => (string) ($row['php'] ?? $row['phpRequirement'] ?? '>=8.4'),
                'downloadUrl' => (string) ($resolved ?? $url),
                'changelogMd' => (string) ($row['changelogMd'] ?? ''),
            ];
        }
        $this->writeCmsReleasesCache($out);

        return $out;
    }

    /**
     * Check for CMS + plugin + theme updates and report anonymous install stats.
     * Network + telemetry at most once per {@see CHECK_TTL_SECONDS}; otherwise returns cache.
     *
     * @param array<string, string> $localVersions pluginKey => version
     * @param array{php?: string, db?: string, locale?: string, install_id?: string} $installInfo
     * @param array<string, string> $localThemes themeKey => version
     * @return array{
     *   plugins: array<string, array{installed: string, latest: string, downloadUrl: string|null}>,
     *   themes: array<string, array{installed: string, latest: string, downloadUrl: string|null}>,
     *   cms: array{latest: string, phpRequirement: string, downloadUrl: string, updateAvailable: bool}|null
     * }
     */
    public function checkForUpdates(
        array $localVersions,
        array $installInfo = [],
        bool $force = false,
        array $localThemes = [],
    ): array {
        if (!$this->configured()) {
            return ['plugins' => [], 'themes' => [], 'cms' => null];
        }

        $fingerprint = $this->fingerprint($localVersions, $installInfo, $localThemes);
        $cached = $this->readCache();
        $now = time();
        $expiresAt = (int) ($cached['expiresAt'] ?? ((int) ($cached['checkedAt'] ?? 0) + self::CHECK_TTL_SECONDS));
        // TTL wins over fingerprint — incomplete maps from different admin pages must not
        // trigger another directory hit while a success/rate-limit window is still open.
        if (!$force && $cached !== null && $expiresAt > $now) {
            return $this->normalizeUpdatePayload(
                is_array($cached['plugins'] ?? null) ? $cached['plugins'] : [],
                $cached['cms'] ?? null,
                is_array($cached['themes'] ?? null) ? $cached['themes'] : [],
            );
        }

        $body = [
            'install_id' => (string) ($installInfo['install_id'] ?? ''),
            'nexis' => $this->coreVersion,
            'php' => (string) ($installInfo['php'] ?? PHP_VERSION),
            'db' => (string) ($installInfo['db'] ?? ''),
            'locale' => (string) ($installInfo['locale'] ?? ''),
            'plugins' => $localVersions,
            'themes' => $localThemes,
        ];

        try {
            /** @var array{cms?: mixed, plugins?: mixed, themes?: mixed, telemetry?: mixed} $data */
            $data = $this->postJson(
                '/api/v1/update-check',
                $body,
                self::CHECK_HTTP_TIMEOUT,
                self::CHECK_CONNECT_TIMEOUT,
            );
        } catch (RuntimeException $e) {
            $rateLimited = str_contains($e->getMessage(), '429');
            if ($rateLimited) {
                $this->logger?->info('Marketplace update-check rate-limited', [
                    'exception' => $e->getMessage(),
                    'backoffSeconds' => self::CHECK_RATE_LIMIT_TTL_SECONDS,
                ]);
            } else {
                $this->logger?->warning('Marketplace update-check failed', ['exception' => $e->getMessage()]);
            }
            $fallback = $cached !== null
                ? $this->normalizeUpdatePayload(
                    is_array($cached['plugins'] ?? null) ? $cached['plugins'] : [],
                    $cached['cms'] ?? null,
                    is_array($cached['themes'] ?? null) ? $cached['themes'] : [],
                )
                : ['plugins' => [], 'themes' => [], 'cms' => null];
            // Soft-fail cache: avoid blocking every subsequent admin page with another wait.
            $ttl = $rateLimited ? self::CHECK_RATE_LIMIT_TTL_SECONDS : self::CHECK_FAILURE_TTL_SECONDS;
            $this->writeCache([
                'checkedAt' => time(),
                'expiresAt' => time() + $ttl,
                'fingerprint' => $fingerprint,
                'plugins' => $fallback['plugins'],
                'themes' => $fallback['themes'],
                'cms' => $fallback['cms'],
                'failed' => true,
                'rateLimited' => $rateLimited,
                'lastError' => $e->getMessage(),
            ]);

            return $fallback;
        }

        $plugins = [];
        $rawPlugins = $data['plugins'] ?? null;
        if (is_array($rawPlugins)) {
            foreach ($rawPlugins as $slug => $row) {
                if (!is_string($slug) || !is_array($row)) {
                    continue;
                }
                $plugins[$slug] = [
                    'installed' => (string) ($row['installed'] ?? ($localVersions[$slug] ?? '')),
                    'latest' => (string) ($row['latest'] ?? ''),
                    'downloadUrl' => $this->resolveDownloadUrl(
                        isset($row['downloadUrl']) && is_string($row['downloadUrl']) ? $row['downloadUrl'] : null,
                    ),
                ];
            }
        }

        $themes = [];
        $rawThemes = $data['themes'] ?? null;
        if (is_array($rawThemes)) {
            foreach ($rawThemes as $slug => $row) {
                if (!is_string($slug) || !is_array($row)) {
                    continue;
                }
                $themes[$slug] = [
                    'installed' => (string) ($row['installed'] ?? ($localThemes[$slug] ?? '')),
                    'latest' => (string) ($row['latest'] ?? ''),
                    'downloadUrl' => $this->resolveDownloadUrl(
                        isset($row['downloadUrl']) && is_string($row['downloadUrl']) ? $row['downloadUrl'] : null,
                    ),
                ];
            }
        }

        $cms = null;
        $rawCms = $data['cms'] ?? null;
        if (is_array($rawCms) && isset($rawCms['latest']) && is_string($rawCms['latest']) && $rawCms['latest'] !== '') {
            $cms = [
                'latest' => $rawCms['latest'],
                'phpRequirement' => (string) ($rawCms['phpRequirement'] ?? ''),
                'downloadUrl' => (string) ($this->resolveDownloadUrl(
                    isset($rawCms['downloadUrl']) && is_string($rawCms['downloadUrl']) ? $rawCms['downloadUrl'] : null,
                ) ?? ''),
                'updateAvailable' => (bool) ($rawCms['updateAvailable'] ?? false),
            ];
        }

        $result = ['plugins' => $plugins, 'themes' => $themes, 'cms' => $cms];
        $telemetry = is_array($data['telemetry'] ?? null) ? $data['telemetry'] : [];
        $recorded = (bool) ($telemetry['recorded'] ?? true);
        // If the directory could not store stats, retry sooner than the normal 12h window.
        $ttl = $recorded ? self::CHECK_TTL_SECONDS : 900;
        $this->writeCache([
            'checkedAt' => time(),
            'expiresAt' => time() + $ttl,
            'fingerprint' => $fingerprint,
            'plugins' => $plugins,
            'themes' => $themes,
            'cms' => $cms,
            'telemetryRecorded' => $recorded,
            'failed' => false,
            'lastError' => null,
        ]);

        $pluginUpdates = 0;
        foreach ($plugins as $row) {
            if ($row['latest'] !== '' && $row['latest'] !== $row['installed']) {
                $pluginUpdates++;
            }
        }
        $themeUpdates = 0;
        foreach ($themes as $row) {
            if ($row['latest'] !== '' && $row['latest'] !== $row['installed']) {
                $themeUpdates++;
            }
        }
        $this->logger?->info('Marketplace update-check ok', [
            'ttlSeconds' => $ttl,
            'telemetryRecorded' => $recorded,
            'pluginUpdates' => $pluginUpdates,
            'themeUpdates' => $themeUpdates,
            'cmsUpdate' => (bool) ($cms['updateAvailable'] ?? false),
            'cmsLatest' => $cms['latest'] ?? null,
        ]);

        return $result;
    }

    /**
     * @param array<string, string> $localVersions
     * @param array{php?: string, db?: string, locale?: string, install_id?: string} $installInfo
     * @param array<string, string> $localThemes
     */
    private function fingerprint(array $localVersions, array $installInfo, array $localThemes = []): string
    {
        ksort($localVersions);
        ksort($localThemes);

        // Only package identity — php/db/locale still go in the POST body for telemetry,
        // but must not bust the cache (callers used to pass inconsistent db strings).
        return hash('sha256', (string) json_encode([
            'install_id' => (string) ($installInfo['install_id'] ?? ''),
            'nexis' => $this->coreVersion,
            'plugins' => $localVersions,
            'themes' => $localThemes,
        ], JSON_THROW_ON_ERROR));
    }

    private function cacheFile(): string
    {
        if ($this->cacheDir === '') {
            return '';
        }

        return rtrim($this->cacheDir, '/\\') . DIRECTORY_SEPARATOR . 'marketplace-update-check.json';
    }

    private function cmsReleasesCacheFile(): string
    {
        if ($this->cacheDir === '') {
            return '';
        }

        return rtrim($this->cacheDir, '/\\') . DIRECTORY_SEPARATOR . 'marketplace-cms-releases.json';
    }

    /**
     * @return list<array{version: string, phpRequirement: string, downloadUrl: string, changelogMd: string}>|null
     */
    private function readCmsReleasesCache(): ?array
    {
        $path = $this->cmsReleasesCacheFile();
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($decoded)) {
            return null;
        }
        $expiresAt = (int) ($decoded['expiresAt'] ?? 0);
        if ($expiresAt > 0 && $expiresAt <= time()) {
            return null;
        }
        $items = $decoded['items'] ?? null;
        if (!is_array($items)) {
            return null;
        }
        $out = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $version = trim((string) ($row['version'] ?? ''));
            $url = trim((string) ($row['downloadUrl'] ?? ''));
            if ($version === '' || $url === '') {
                continue;
            }
            $out[] = [
                'version' => $version,
                'phpRequirement' => (string) ($row['phpRequirement'] ?? '>=8.4'),
                'downloadUrl' => $url,
                'changelogMd' => (string) ($row['changelogMd'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{version: string, phpRequirement: string, downloadUrl: string, changelogMd: string}> $items
     */
    private function writeCmsReleasesCache(array $items): void
    {
        $path = $this->cmsReleasesCacheFile();
        if ($path === '') {
            return;
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        try {
            $json = json_encode([
                'expiresAt' => time() + self::CHECK_TTL_SECONDS,
                'items' => $items,
            ], JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }
        @file_put_contents($path, $json);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readCache(): ?array
    {
        $path = $this->cacheFile();
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeCache(array $payload): void
    {
        $path = $this->cacheFile();
        if ($path === '') {
            return;
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }
        @file_put_contents($path, $json);
    }

    /**
     * Compare local installed versions against directory latest.
     *
     * @param array<string, string> $localVersions pluginKey => version
     * @return array<string, array{installed: string, latest: string, downloadUrl: string|null}>
     */
    public function findUpdates(array $localVersions): array
    {
        if (!$this->configured() || $localVersions === []) {
            return [];
        }

        $updates = [];
        $page = 1;
        $pages = 1;
        do {
            $result = $this->search('', $page, 50);
            $pages = max(1, (int) ceil($result['total'] / max(1, $result['perPage'])));
            foreach ($result['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $slug = (string) ($item['slug'] ?? '');
                if ($slug === '' || !isset($localVersions[$slug])) {
                    continue;
                }
                $latest = trim((string) ($item['version'] ?? ''));
                $installed = trim($localVersions[$slug]);
                if ($latest === '' || $installed === '') {
                    continue;
                }
                if (version_compare($this->normalizeVersion($latest), $this->normalizeVersion($installed), '>')) {
                    $download = $item['downloadUrl'] ?? null;
                    $compatibleCore = trim((string) ($item['compatibleCore'] ?? ''));
                    $updates[$slug] = [
                        'installed' => $installed,
                        'latest' => $latest,
                        'downloadUrl' => is_string($download) ? $download : null,
                        'compatibleCore' => $compatibleCore,
                        'coreCompatible' => PluginCoreCompatibility::isCompatibleWithCore(
                            $compatibleCore,
                            $this->coreVersion,
                        ),
                    ];
                }
            }
            $page++;
        } while ($page <= $pages);

        return $updates;
    }

    private function normalizeVersion(string $version): string
    {
        return ltrim(trim($version), 'vV');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function plugin(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '' || !str_contains($slug, '/')) {
            return null;
        }
        [$vendor, $name] = explode('/', $slug, 2);
        try {
            return $this->getJson('/api/v1/plugins/' . rawurlencode($vendor) . '/' . rawurlencode($name));
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), '404')) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Downloads a release ZIP to a local path. Returns sha256 of the file.
     */
    public function downloadRelease(string $slug, string $version, string $targetZipPath): string
    {
        $slug = trim($slug);
        $version = trim($version);
        if ($slug === '' || !str_contains($slug, '/') || $version === '') {
            throw new RuntimeException('Invalid marketplace plugin reference.');
        }
        [$vendor, $name] = explode('/', $slug, 2);
        $path = '/api/v1/plugins/' . rawurlencode($vendor) . '/' . rawurlencode($name)
            . '/download/' . rawurlencode($version);
        $this->download($path, $targetZipPath);
        $sha = hash_file('sha256', $targetZipPath);
        if ($sha === false) {
            throw new RuntimeException('Downloaded package unreadable.');
        }

        return $sha;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function theme(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '' || !str_contains($slug, '/')) {
            return null;
        }
        [$vendor, $name] = explode('/', $slug, 2);
        try {
            return $this->getJson('/api/v1/themes/' . rawurlencode($vendor) . '/' . rawurlencode($name));
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), '404')) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Downloads a theme release ZIP. Returns sha256 of the file.
     */
    public function downloadThemeRelease(string $slug, string $version, string $targetZipPath): string
    {
        $slug = trim($slug);
        $version = trim($version);
        if ($slug === '' || !str_contains($slug, '/') || $version === '') {
            throw new RuntimeException('Invalid marketplace theme reference.');
        }
        [$vendor, $name] = explode('/', $slug, 2);
        $path = '/api/v1/themes/' . rawurlencode($vendor) . '/' . rawurlencode($name)
            . '/download/' . rawurlencode($version);
        $this->download($path, $targetZipPath);
        $sha = hash_file('sha256', $targetZipPath);
        if ($sha === false) {
            throw new RuntimeException('Downloaded package unreadable.');
        }

        return $sha;
    }

    /**
     * @param array<string, scalar> $query
     * @return array<string, mixed>
     */
    private function getJson(
        string $path,
        array $query = [],
        int $timeout = 60,
        int $connectTimeout = 5,
    ): array {
        $body = $this->request('GET', $path, $query, null, false, $timeout, $connectTimeout);
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Marketplace invalid JSON', 0, $e);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Marketplace invalid payload');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function postJson(
        string $path,
        array $payload,
        int $timeout = 60,
        int $connectTimeout = 5,
    ): array {
        $body = $this->request('POST', $path, [], $payload, false, $timeout, $connectTimeout);
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Marketplace invalid JSON', 0, $e);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Marketplace invalid payload');
        }

        return $decoded;
    }

    /**
     * Download any absolute or directory-relative URL to a local file. Returns SHA-256.
     */
    public function downloadUrlToFile(string $url, string $targetFile): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new RuntimeException('Empty download URL.');
        }
        if (!preg_match('#^https?://#i', $url)) {
            if (!str_starts_with($url, '/')) {
                $url = '/' . $url;
            }
            if ($this->baseUrl === '') {
                throw new RuntimeException('Plugin directory URL is not configured.');
            }
            $url = $this->baseUrl . $url;
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('ext-curl fehlt für Marketplace-Client.');
        }
        $dir = dirname($targetFile);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create download directory.');
        }
        $fp = fopen($targetFile, 'wb');
        if ($fp === false) {
            throw new RuntimeException('Cannot write download target.');
        }
        $ch = curl_init($url);
        if ($ch === false) {
            fclose($fp);
            throw new RuntimeException('curl_init failed');
        }
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Nexis-CMS/' . ($this->coreVersion !== '' ? $this->coreVersion : 'dev'),
            ],
        ]);
        $ok = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        fclose($fp);
        if ($ok !== true || $status < 200 || $status >= 300) {
            @unlink($targetFile);
            $this->logger?->error('Marketplace download failed', ['url' => $url, 'status' => $status, 'error' => $err]);
            throw new RuntimeException('Download failed (HTTP ' . $status . ')' . ($err !== '' ? ': ' . $err : ''));
        }
        $sha = hash_file('sha256', $targetFile);
        if ($sha === false || filesize($targetFile) <= 0) {
            @unlink($targetFile);
            throw new RuntimeException('Downloaded package unreadable.');
        }

        return $sha;
    }

    /**
     * @param array<string, scalar> $query
     */
    private function download(string $path, string $targetFile, array $query = []): void
    {
        $url = $path;
        if ($query !== []) {
            $url .= (str_contains($path, '?') ? '&' : '?') . http_build_query($query);
        }
        $this->downloadUrlToFile($url, $targetFile);
    }

    /**
     * @param array<string, scalar> $query
     * @param array<string, mixed>|null $jsonBody
     */
    /**
     * @param array<string, scalar> $query
     * @param array<string, mixed>|null $jsonBody
     */
    private function request(
        string $method,
        string $path,
        array $query = [],
        ?array $jsonBody = null,
        bool $binary = false,
        int $timeout = 60,
        int $connectTimeout = 5,
    ): string {
        if ($this->baseUrl === '') {
            throw new RuntimeException('Plugin directory URL is not configured.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('ext-curl fehlt für Marketplace-Client.');
        }
        $url = $this->baseUrl . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }
        $headers = [
            'User-Agent: Nexis-CMS/' . ($this->coreVersion !== '' ? $this->coreVersion : 'dev'),
        ];
        if (!$binary) {
            $headers[] = 'Accept: application/json';
        }
        $methodUpper = strtoupper($method);
        if ($methodUpper === '') {
            throw new RuntimeException('HTTP method required');
        }
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => max(1, $timeout),
            CURLOPT_CONNECTTIMEOUT => max(1, $connectTimeout),
            CURLOPT_CUSTOMREQUEST => $methodUpper,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($jsonBody !== null) {
            $encoded = json_encode($jsonBody, JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = $encoded;
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        if (!is_string($body)) {
            $this->logger?->error('Marketplace request failed', ['url' => $url, 'error' => $err]);
            throw new RuntimeException('Marketplace unreachable: ' . $err);
        }
        if ($status === 404) {
            throw new RuntimeException('Marketplace 404: ' . $path);
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Marketplace HTTP ' . $status);
        }

        return $body;
    }

    /**
     * @param array<mixed> $rawPlugins
     * @param array<mixed> $rawThemes
     * @return array{
     *   plugins: array<string, array{installed: string, latest: string, downloadUrl: string|null}>,
     *   themes: array<string, array{installed: string, latest: string, downloadUrl: string|null}>,
     *   cms: array{latest: string, phpRequirement: string, downloadUrl: string, updateAvailable: bool}|null
     * }
     */
    private function normalizeUpdatePayload(array $rawPlugins, mixed $rawCms, array $rawThemes = []): array
    {
        $plugins = [];
        foreach ($rawPlugins as $slug => $row) {
            if (!is_string($slug) || !is_array($row)) {
                continue;
            }
            $plugins[$slug] = [
                'installed' => (string) ($row['installed'] ?? ''),
                'latest' => (string) ($row['latest'] ?? ''),
                'downloadUrl' => $this->resolveDownloadUrl(
                    isset($row['downloadUrl']) && is_string($row['downloadUrl']) ? $row['downloadUrl'] : null,
                ),
            ];
        }

        $themes = [];
        foreach ($rawThemes as $slug => $row) {
            if (!is_string($slug) || !is_array($row)) {
                continue;
            }
            $themes[$slug] = [
                'installed' => (string) ($row['installed'] ?? ''),
                'latest' => (string) ($row['latest'] ?? ''),
                'downloadUrl' => $this->resolveDownloadUrl(
                    isset($row['downloadUrl']) && is_string($row['downloadUrl']) ? $row['downloadUrl'] : null,
                ),
            ];
        }

        $cms = null;
        if (is_array($rawCms) && isset($rawCms['latest']) && is_string($rawCms['latest']) && $rawCms['latest'] !== '') {
            $cms = [
                'latest' => $rawCms['latest'],
                'phpRequirement' => (string) ($rawCms['phpRequirement'] ?? ''),
                'downloadUrl' => (string) ($this->resolveDownloadUrl(
                    isset($rawCms['downloadUrl']) && is_string($rawCms['downloadUrl']) ? $rawCms['downloadUrl'] : null,
                ) ?? ''),
                'updateAvailable' => (bool) ($rawCms['updateAvailable'] ?? false),
            ];
        }

        return ['plugins' => $plugins, 'themes' => $themes, 'cms' => $cms];
    }

    /**
     * Absolute directory download URLs for browser links. Relative paths would resolve
     * against the CMS origin and break (e.g. http://local-ip/download/cms/…).
     */
    private function resolveDownloadUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }
        if ($this->baseUrl === '') {
            return $url;
        }
        if (str_starts_with($url, '/')) {
            return $this->baseUrl . $url;
        }

        return $this->baseUrl . '/' . ltrim($url, '/');
    }
}
