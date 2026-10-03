<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Kernel\Config;
use Nexis\Kernel\Nexis;
use Nexis\Plugin\MarketplaceClient;
use Nexis\Plugin\PluginCatalog;
use Nexis\Plugin\PluginInstallStatus;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Nexis\Theme\ThemeCatalog;
use Nexis\Theme\ThemeDiscovery;
use Nexis\Update\CmsPackageUpdater;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class AboutAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private Config $config,
        private PDO $pdo,
        private PluginCatalog $plugins,
        private ThemeCatalog $themes,
        private ThemeDiscovery $themeDiscovery,
        private AdminUi $ui,
        private MarketplaceClient $marketplace,
        private CmsPackageUpdater $cmsUpdater,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return $this->index($request);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $gate = $this->authorize($request);
        if ($gate instanceof ResponseInterface) {
            return $gate;
        }
        [$user, $site, $basePath] = $gate;

        $enabled = 0;
        foreach ($this->plugins->listForSite($site->id) as $plugin) {
            if ($plugin['status'] === PluginInstallStatus::Enabled->value) {
                $enabled++;
            }
        }

        $themeKey = (string) ($this->themes->themeKeyForSite($site->id) ?? '');
        $cmsUpdate = null;
        $cmsReleases = [];
        $updateCheckError = RequestInput::query($request, 'update_error');
        $updateChecked = RequestInput::query($request, 'update_checked') === '1';
        $upgradeNotice = RequestInput::query($request, 'upgraded');
        $upgradeError = RequestInput::query($request, 'upgrade_error');
        if ($this->marketplace->configured()) {
            try {
                $cmsUpdate = $this->runUpdateCheck($site, false)['cms'];
            } catch (Throwable) {
                // Optional cached status; manual check surfaces errors.
            }
            try {
                $cmsReleases = $this->olderCmsReleases(Nexis::VERSION);
            } catch (Throwable) {
                $cmsReleases = [];
            }
        }

        return $this->responses->html($this->views->render('admin.about.index', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'productName' => Nexis::NAME,
            'productVersion' => Nexis::VERSION,
            'tagline' => Nexis::TAGLINE,
            'vendor' => Nexis::VENDOR,
            'vendorUrl' => Nexis::VENDOR_URL,
            'attribution' => Nexis::ATTRIBUTION,
            'copyright' => Nexis::copyrightNotice(),
            'license' => Nexis::LICENSE,
            'licenseName' => Nexis::LICENSE_NAME,
            'licenseUri' => Nexis::LICENSE_URI,
            'phpVersion' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'appEnv' => $this->config->envName(),
            'appDebug' => $this->config->debug(),
            'appUrl' => (string) $this->config->get('app.url', ''),
            'dbDriver' => (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
            'dbServer' => $this->databaseServerVersion(),
            'enabledPlugins' => $enabled,
            'themeKey' => $themeKey,
            'os' => PHP_OS_FAMILY,
            'marketplaceConfigured' => $this->marketplace->configured(),
            'cmsUpdate' => $cmsUpdate,
            'cmsReleases' => $cmsReleases,
            'updateChecked' => $updateChecked,
            'updateCheckError' => $updateCheckError,
            'canUpgrade' => $this->policy->can($user, $site, Permission::SETTINGS_MANAGE),
            'upgradeNotice' => $upgradeNotice,
            'upgradeError' => $upgradeError,
        ], 'admin.layout'));
    }

    public function checkUpdate(ServerRequestInterface $request): ResponseInterface
    {
        $gate = $this->authorize($request);
        if ($gate instanceof ResponseInterface) {
            return $gate;
        }
        [$user, $site, $basePath] = $gate;

        if (!$this->marketplace->configured()) {
            return $this->responses->redirect(
                $basePath . '/admin/about?update_error=' . rawurlencode(
                    $this->ui->get($user, 'admin.about.update_unavailable'),
                ),
            );
        }

        try {
            $this->runUpdateCheck($site, true);
        } catch (Throwable) {
            return $this->responses->redirect(
                $basePath . '/admin/about?update_error=' . rawurlencode(
                    $this->ui->get($user, 'admin.about.update_failed'),
                ),
            );
        }

        return $this->responses->redirect($basePath . '/admin/about?update_checked=1');
    }

    public function upgrade(ServerRequestInterface $request): ResponseInterface
    {
        $gate = $this->authorize($request);
        if ($gate instanceof ResponseInterface) {
            return $gate;
        }
        [$user, $site, $basePath] = $gate;

        if (!$this->policy->can($user, $site, Permission::SETTINGS_MANAGE)) {
            return $this->responses->html(
                $this->ui->get($user, 'admin.error.no_access_permission', ['permission' => Permission::SETTINGS_MANAGE]),
                403,
            );
        }
        if (!$this->marketplace->configured()) {
            return $this->responses->redirect(
                $basePath . '/admin/about?upgrade_error=' . rawurlencode(
                    $this->ui->get($user, 'admin.about.update_unavailable'),
                ),
            );
        }

        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

        $requested = trim(RequestInput::string($request, 'version'));

        try {
            $cms = null;
            if ($requested !== '') {
                foreach ($this->marketplace->listCmsReleases() as $row) {
                    if ($row['version'] === $requested) {
                        $cms = [
                            'latest' => $row['version'],
                            'phpRequirement' => $row['phpRequirement'],
                            'downloadUrl' => $row['downloadUrl'],
                            'updateAvailable' => true,
                        ];
                        break;
                    }
                }
                if ($cms === null) {
                    return $this->responses->redirect(
                        $basePath . '/admin/about?upgrade_error=' . rawurlencode(
                            $this->ui->get($user, 'admin.about.upgrade_version_missing', ['version' => $requested]),
                        ),
                    );
                }
            } else {
                $check = $this->runUpdateCheck($site, true);
                $cms = $check['cms'];
                if (!is_array($cms) || empty($cms['updateAvailable']) || ($cms['downloadUrl'] ?? '') === '') {
                    return $this->responses->redirect(
                        $basePath . '/admin/about?upgrade_error=' . rawurlencode(
                            $this->ui->get($user, 'admin.about.upgrade_none'),
                        ),
                    );
                }
            }
            $result = $this->cmsUpdater->upgradeFromCatalog($cms, $site->id);
        } catch (Throwable $e) {
            return $this->responses->redirect(
                $basePath . '/admin/about?upgrade_error=' . rawurlencode(
                    $this->ui->get($user, 'admin.about.upgrade_failed', ['message' => $e->getMessage()]),
                ),
            );
        }

        $msgKey = version_compare(ltrim($result['to'], 'vV'), ltrim($result['from'], 'vV'), '<')
            ? 'admin.about.downgrade_ok'
            : 'admin.about.upgrade_ok';

        return $this->responses->redirect(
            $basePath . '/admin/about?upgraded=' . rawurlencode(
                $this->ui->get($user, $msgKey, [
                    'from' => $result['from'],
                    'to' => $result['to'],
                    'stamp' => $result['backupStamp'],
                ]),
            ),
        );
    }

    /**
     * @return list<array{version: string, phpRequirement: string, downloadUrl: string, changelogMd: string}>
     */
    private function olderCmsReleases(string $installed): array
    {
        $out = [];
        foreach ($this->marketplace->listCmsReleases() as $row) {
            if (version_compare(ltrim($row['version'], 'vV'), ltrim($installed, 'vV'), '<')) {
                $out[] = $row;
            }
        }

        return $out;
    }

    public function license(ServerRequestInterface $request): ResponseInterface
    {
        $gate = $this->authorize($request);
        if ($gate instanceof ResponseInterface) {
            return $gate;
        }
        [$user, $site, $basePath] = $gate;

        $path = $this->config->rootPath . DIRECTORY_SEPARATOR . 'LICENSE';
        $text = is_readable($path) ? (string) file_get_contents($path) : '';
        if ($text === '') {
            return $this->responses->html('LICENSE-Datei fehlt.', 404);
        }

        return $this->responses->html($this->views->render('admin.about.license', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'copyright' => Nexis::copyrightNotice(),
            'licenseName' => Nexis::LICENSE_NAME,
            'licenseText' => $text,
        ], 'admin.layout'));
    }

    /**
     * @return array{
     *   plugins: array<string, array{installed: string, latest: string, downloadUrl: string|null}>,
     *   themes: array<string, array{installed: string, latest: string, downloadUrl: string|null}>,
     *   cms: array{latest: string, phpRequirement: string, downloadUrl: string, updateAvailable: bool}|null
     * }
     */
    private function runUpdateCheck(Site $site, bool $force): array
    {
        $localVersions = [];
        foreach ($this->plugins->listForSite($site->id) as $row) {
            $key = (string) ($row['key'] ?? '');
            $version = (string) ($row['version'] ?? '');
            if ($key !== '' && $version !== '' && ($row['status'] ?? '') !== 'discovered') {
                $localVersions[$key] = $version;
            }
        }

        $localThemes = [];
        foreach ($this->themeDiscovery->discover() as $manifest) {
            if ($manifest->id !== '' && $manifest->version !== '') {
                $localThemes[$manifest->id] = $manifest->version;
            }
        }

        $dbVersion = '';
        try {
            $stmt = $this->pdo->query('SELECT VERSION()');
            if ($stmt !== false) {
                $dbVersion = (string) $stmt->fetchColumn();
            }
        } catch (Throwable) {
            $dbVersion = '';
        }

        return $this->marketplace->checkForUpdates($localVersions, [
            'install_id' => $site->id->value,
            'php' => PHP_VERSION,
            'db' => $dbVersion,
            'locale' => $site->defaultLocale,
        ], $force, $localThemes);
    }

    /**
     * @return ResponseInterface|array{0: User, 1: Site, 2: string}
     */
    private function authorize(ServerRequestInterface $request): ResponseInterface|array
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

    private function databaseServerVersion(): string
    {
        try {
            $stmt = $this->pdo->query('SELECT VERSION()');
            if ($stmt === false) {
                return 'unbekannt';
            }
            $version = $stmt->fetchColumn();

            return is_string($version) ? $version : 'unbekannt';
        } catch (\Throwable) {
            return 'unbekannt';
        }
    }
}
