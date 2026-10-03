<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Audit\AuditLogger;
use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Cache\PageCache;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Media\MediaRepository;
use Nexis\Plugin\MarketplaceClient;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Nexis\Theme\ThemeCatalog;
use Nexis\Theme\ThemeDiscovery;
use Nexis\Theme\ThemePackageInstaller;
use Nexis\Theme\ThemeService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class ThemeAdminController
{
    /** @var list<string> */
    private const BRANDING_KEYS = [
        'layout.nav.position',
        'layout.footer.position',
        'layout.pageTitle',
        'layout.footer.showSiteName',
        'layout.footer.showThemeName',
        'brand.showSiteName',
        'brand.logoInNav',
        'color.brand.primary',
        'color.brand.accent',
        'color.surface',
        'color.text',
        'brand.logoUrl',
        'font.sans',
        'layout.max',
    ];

    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private ThemeService $themes,
        private ThemeDiscovery $discovery,
        private ThemeCatalog $catalog,
        private ThemePackageInstaller $installer,
        private MarketplaceClient $marketplace,
        private MediaRepository $media,
        private PageCache $cache,
        private AuditLogger $audit,
        private AdminUi $ui,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $this->themes->sync();
        $active = $this->catalog->themeKeyForSite($site->id) ?? 'nexis/nexis';
        $resolved = $this->themes->resolveFor($site, $basePath);
        $overrides = $this->catalog->overrides($site->id);

        $notice = '';
        if (RequestInput::query($request, 'saved') === '1') {
            $notice = $this->ui->get($user, 'admin.common.saved');
        } elseif (RequestInput::query($request, 'installed') === '1') {
            $themeKey = RequestInput::query($request, 'theme', '');
            $notice = $themeKey !== ''
                ? $this->ui->get($user, 'admin.theme.notice.installed', ['theme' => $themeKey])
                : $this->ui->get($user, 'admin.theme.notice.installed_plain');
        }

        $marketplaceUpdates = [];
        if ($this->marketplace->configured()) {
            $localThemes = [];
            foreach ($this->discovery->discover() as $manifest) {
                if ($manifest->id !== '' && $manifest->version !== '') {
                    $localThemes[$manifest->id] = $manifest->version;
                }
            }
            try {
                $check = $this->marketplace->checkForUpdates([], [
                    'install_id' => $site->id->value,
                    'php' => PHP_VERSION,
                    'db' => '',
                    'locale' => $site->defaultLocale,
                ], false, $localThemes);
                $marketplaceUpdates = $check['themes'] ?? [];
            } catch (Throwable $e) {
                $this->logger?->warning('Theme marketplace update check failed', ['exception' => $e->getMessage()]);
            }
        }

        return $this->responses->html($this->views->render('admin.theme.index', [
            'user' => $user,
            'site' => $site,
            'themes' => $this->discovery->discover(),
            'activeTheme' => $active,
            'tokens' => $resolved['tokens'],
            'overrides' => $overrides,
            'customCss' => $this->catalog->customCss($site->id),
            'logoMedia' => $this->logoMediaCatalog($site, $basePath),
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'notice' => $notice,
            'marketplaceConfigured' => $this->marketplace->configured(),
            'marketplaceUrl' => $this->marketplace->baseUrl(),
            'marketplaceUpdates' => $marketplaceUpdates,
        ], 'admin.layout'));
    }

    public function marketplace(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        if (!$this->policy->can($user, $site, Permission::THEME_MANAGE)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'theme.manage']), 403);
        }

        $q = trim(RequestInput::query($request, 'q', ''));
        $page = max(1, (int) RequestInput::query($request, 'page', '1'));
        $marketplaceError = RequestInput::query($request, 'error');
        $result = ['items' => [], 'total' => 0, 'page' => 1, 'perPage' => 20];
        if ($this->marketplace->configured()) {
            try {
                $result = $this->marketplace->searchThemes($q, $page);
            } catch (RuntimeException $e) {
                $this->logger?->warning('Theme marketplace browse failed', ['exception' => $e->getMessage()]);
                if ($marketplaceError === '') {
                    $marketplaceError = $this->ui->get($user, 'admin.theme.marketplace.unreachable');
                }
            }
        }

        $installed = [];
        $installedVersions = [];
        foreach ($this->discovery->discover() as $manifest) {
            $installed[$manifest->id] = 'installed';
            $installedVersions[$manifest->id] = $manifest->version;
        }

        return $this->responses->html($this->views->render('admin.theme.marketplace', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'canInstall' => $this->policy->can($user, $site, Permission::THEME_MANAGE),
            'configured' => $this->marketplace->configured(),
            'marketplaceUrl' => $this->marketplace->baseUrl(),
            'q' => $q,
            'result' => $result,
            'installed' => $installed,
            'installedVersions' => $installedVersions,
            'marketplaceError' => $marketplaceError,
        ], 'admin.layout'));
    }

    public function installFromMarketplace(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        if (!$this->policy->can($user, $site, Permission::THEME_MANAGE)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'theme.manage']), 403);
        }
        if (!$this->marketplace->configured()) {
            return $this->responses->redirect($basePath . '/admin/theme/marketplace?error=' . rawurlencode(
                $this->ui->get($user, 'admin.theme.marketplace.not_configured'),
            ));
        }

        $slug = trim(RequestInput::string($request, 'theme'));
        $overwrite = RequestInput::string($request, 'overwrite') === '1';
        if ($slug === '' || !str_contains($slug, '/')) {
            return $this->responses->redirect($basePath . '/admin/theme/marketplace?error=' . rawurlencode(
                $this->ui->get($user, 'admin.theme.marketplace.invalid_theme'),
            ));
        }

        try {
            $detail = $this->marketplace->theme($slug);
            if ($detail === null) {
                return $this->responses->redirect($basePath . '/admin/theme/marketplace?error=' . rawurlencode(
                    $this->ui->get($user, 'admin.theme.marketplace.not_found'),
                ));
            }
            $latest = is_array($detail['latest'] ?? null) ? $detail['latest'] : [];
            $version = (string) ($latest['version'] ?? '');
            $expectedSha = (string) ($latest['packageSha256'] ?? '');
            if ($version === '' || ($latest['downloadUrl'] ?? null) === null) {
                return $this->responses->redirect($basePath . '/admin/theme/marketplace?error=' . rawurlencode(
                    $this->ui->get($user, 'admin.theme.marketplace.no_package'),
                ));
            }

            $tmp = tempnam(sys_get_temp_dir(), 'nexis-thm-');
            if ($tmp === false) {
                return $this->responses->redirect($basePath . '/admin/theme/marketplace?error=' . rawurlencode(
                    $this->ui->get($user, 'admin.error.plugins_temp_failed'),
                ));
            }
            $zipPath = $tmp . '.zip';
            rename($tmp, $zipPath);

            try {
                $sha = $this->marketplace->downloadThemeRelease($slug, $version, $zipPath);
                if ($expectedSha !== '' && !hash_equals($expectedSha, $sha)) {
                    throw new RuntimeException($this->ui->get($user, 'admin.theme.marketplace.checksum_mismatch'));
                }
                $result = $this->installer->installFromZip($zipPath, $overwrite);
                $this->themes->sync();
                $this->audit->log('theme.marketplace.installed', $site->id, $user->id, 'theme', null, [
                    'theme' => $result['manifest']->id,
                    'version' => $result['manifest']->version,
                    'source' => 'marketplace',
                ]);

                return $this->responses->redirect(
                    $basePath . '/admin/theme?installed=1&theme=' . rawurlencode($result['manifest']->id) . '#theme',
                );
            } finally {
                @unlink($zipPath);
            }
        } catch (Throwable $e) {
            $this->logger?->warning('Theme marketplace install failed', [
                'theme' => $slug,
                'exception' => $e->getMessage(),
            ]);

            return $this->responses->redirect($basePath . '/admin/theme/marketplace?error=' . rawurlencode($e->getMessage()));
        }
    }

    public function activate(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        if (!$this->policy->can($user, $site, Permission::THEME_MANAGE)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'theme.manage']), 403);
        }
        $key = RequestInput::string($request, 'theme');
        $this->themes->sync();
        $previous = $this->catalog->themeKeyForSite($site->id);
        $this->catalog->assignTheme($site->id, $key);
        $this->clearBrandingOverrides($site, $user->id->value);
        $this->cache->invalidateSite($site->id);
        $this->audit->log('theme.activate', $site->id, $user->id, 'theme', null, [
            'theme' => $key,
            'previous' => $previous,
            'branding_reset' => true,
        ]);

        return $this->responses->redirect($basePath . '/admin/theme?saved=1#theme');
    }

    public function saveBranding(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        if (!$this->policy->can($user, $site, Permission::THEME_MANAGE)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'theme.manage']), 403);
        }
        $tokens = $this->catalog->overrides($site->id);
        $defaults = $this->themes->defaultsFor($site);
        $selectKeys = [
            'layout.nav.position',
            'layout.footer.position',
            'layout.pageTitle',
            'layout.footer.showSiteName',
            'layout.footer.showThemeName',
            'brand.showSiteName',
            'brand.logoInNav',
        ];
        $textKeys = [
            'color.brand.primary',
            'color.brand.accent',
            'color.surface',
            'color.text',
            'brand.logoUrl',
            'font.sans',
            'layout.max',
        ];
        foreach ([...$selectKeys, ...$textKeys] as $key) {
            $field = 'token_' . str_replace('.', '_', $key);
            $value = RequestInput::string($request, $field);
            if (in_array($key, $selectKeys, true)) {
                $allowed = match ($key) {
                    'layout.nav.position' => ['right', 'left', 'below'],
                    'layout.footer.position' => ['split', 'center', 'stack', 'reverse'],
                    'layout.pageTitle' => ['show', 'hide'],
                    default => ['true', 'false'],
                };
                if (!in_array($value, $allowed, true)) {
                    unset($tokens[$key]);
                    continue;
                }
                if ($value === ($defaults[$key] ?? null)) {
                    unset($tokens[$key]);
                } else {
                    $tokens[$key] = $value;
                }
                continue;
            }
            if ($value === '' || $value === ($defaults[$key] ?? null)) {
                unset($tokens[$key]);
            } else {
                $tokens[$key] = $value;
            }
        }
        $customCss = RequestInput::string($request, 'custom_css');
        if (!$this->policy->can($user, $site, Permission::THEME_CUSTOM_CSS)) {
            $customCss = $this->catalog->customCss($site->id);
        }
        $this->catalog->saveOverrides($site->id, $tokens, $customCss !== '' ? $customCss : null, $user->id->value);
        $this->cache->invalidateSite($site->id);
        $this->audit->log('theme.branding.update', $site->id, $user->id, 'site', $site->id->value, [
            'keys' => array_keys($tokens),
        ]);

        $tab = RequestInput::string($request, 'return_tab', 'layout');
        if (!in_array($tab, ['layout', 'colors'], true)) {
            $tab = 'layout';
        }

        return $this->responses->redirect($basePath . '/admin/theme?saved=1#' . $tab);
    }

    private function clearBrandingOverrides(Site $site, string $userId): void
    {
        $tokens = $this->catalog->overrides($site->id);
        foreach (self::BRANDING_KEYS as $key) {
            unset($tokens[$key]);
        }
        $customCss = $this->catalog->customCss($site->id);
        $this->catalog->saveOverrides($site->id, $tokens, $customCss !== '' ? $customCss : null, $userId);
    }

    /**
     * @return list<array{id: string, name: string, alt: string, url: string, thumbUrl: string}>
     */
    private function logoMediaCatalog(Site $site, string $basePath): array
    {
        $out = [];
        foreach ($this->media->listBySite($site->id) as $asset) {
            if (!$asset->isImage()) {
                continue;
            }
            $url = $basePath . '/media/' . $asset->id->value;
            $out[] = [
                'id' => $asset->id->value,
                'name' => $asset->originalName,
                'alt' => $asset->displayAlt(),
                'url' => $url,
                'thumbUrl' => $this->media->hasVariant($asset->id, 'thumb') ? $url . '/thumb' : $url,
            ];
        }

        return $out;
    }

    /**
     * @return array{User, Site, string}|ResponseInterface
     */
    private function context(ServerRequestInterface $request): array|ResponseInterface
    {
        $user = $request->getAttribute('user');
        $basePath = (string) $request->getAttribute('base_path', '');
        if (!$user instanceof User) {
            return $this->responses->redirect($basePath . '/admin/login');
        }
        $site = $this->sites->installed();
        if ($site === null) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_site'), 503);
        }
        if (!$this->policy->view($user, $site)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access'), 403);
        }

        return [$user, $site, $basePath];
    }
}
