<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

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
        private AdminUi $ui,
        private MarketplaceClient $marketplace,
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
        $updateCheckError = RequestInput::query($request, 'update_error');
        $updateChecked = RequestInput::query($request, 'update_checked') === '1';
        if ($this->marketplace->configured()) {
            try {
                $cmsUpdate = $this->runUpdateCheck($site, false)['cms'];
            } catch (Throwable) {
                // Optional cached status; manual check surfaces errors.
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
            'updateChecked' => $updateChecked,
            'updateCheckError' => $updateCheckError,
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
        ], $force);
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
