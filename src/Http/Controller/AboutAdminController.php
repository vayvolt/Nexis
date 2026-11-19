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
use Nexis\Update\CmsUpgradeLauncher;
use Nexis\Update\CmsUpgradeProgress;
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
        private CmsUpgradeProgress $upgradeProgress,
        private CmsUpgradeLauncher $upgradeLauncher,
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
        $marketplaceNeedsRefresh = false;
        $updateCheckError = RequestInput::query($request, 'update_error');
        $updateChecked = RequestInput::query($request, 'update_checked') === '1';
        $upgradeNotice = RequestInput::query($request, 'upgraded');
        $upgradeError = RequestInput::query($request, 'upgrade_error');
        $upgrading = RequestInput::query($request, 'upgrading') === '1';
        $upgradeProgress = $this->upgradeProgress->read();
        if ($this->marketplace->configured()) {
            $peek = $this->marketplace->peekCachedUpdates();
            $cmsUpdate = $peek['cms'];
            $marketplaceNeedsRefresh = $this->needsUpdateRefresh($site);
            // Cache-only on GET — directory HTTP runs via background refresh / manual check.
            try {
                $cmsReleases = $this->olderCmsReleases(Nexis::VERSION, false);
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
            'marketplaceNeedsRefresh' => $marketplaceNeedsRefresh,
            'updateChecked' => $updateChecked,
            'updateCheckError' => $updateCheckError,
            'canUpgrade' => $this->policy->can($user, $site, Permission::SETTINGS_MANAGE),
            'upgradeNotice' => $upgradeNotice,
            'upgradeError' => $upgradeError,
            'upgrading' => $upgrading,
            'upgradeProgress' => $upgradeProgress,
            'upgradeStepLabels' => $this->upgradeStepLabels($user),
        ], 'admin.layout'));
    }

    /**
     * Background update-check for admin pages (JSON). Never used to render HTML.
     */
    public function refreshUpdates(ServerRequestInterface $request): ResponseInterface
    {
        $gate = $this->authorize($request);
        if ($gate instanceof ResponseInterface) {
            return $gate;
        }
        [, $site] = $gate;

        if (!$this->marketplace->configured()) {
            return $this->responses->json(['ok' => false, 'reload' => false, 'error' => 'unconfigured'], 503);
        }

        $before = $this->marketplace->peekCachedUpdates();
        try {
            $this->runUpdateCheck($site, false);
            // Only refresh release list when the update-check itself succeeded.
            $afterCheck = $this->marketplace->peekCachedUpdates();
            if (empty($afterCheck['failed'])) {
                $this->marketplace->listCmsReleases(true);
            }
        } catch (Throwable $e) {
            return $this->responses->json([
                'ok' => false,
                'reload' => false,
                'error' => $e->getMessage(),
            ], 502);
        }
        $after = $this->marketplace->peekCachedUpdates();
        $reload = empty($after['failed']) && (
            json_encode($before['cms']) !== json_encode($after['cms'])
            || json_encode($before['plugins']) !== json_encode($after['plugins'])
            || json_encode($before['themes']) !== json_encode($after['themes'])
            || (!empty($after['cms']['updateAvailable']))
        );

        return $this->responses->json([
            'ok' => empty($after['failed']),
            'reload' => $reload,
            'failed' => !empty($after['failed']),
            'updateAvailable' => !empty($after['cms']['updateAvailable']),
        ]);
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
            $this->marketplace->listCmsReleases(true);
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

        $active = $this->upgradeProgress->read();
        if ($active !== null && in_array($active['status'], ['queued', 'running'], true)) {
            return $this->responses->redirect($basePath . '/admin/about?upgrading=1');
        }

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
            $job = $this->upgradeProgress->queue($cms, $site->id);
            $launch = $this->upgradeLauncher->spawn($job['id']);
            if ($launch['spawned']) {
                $this->upgradeProgress->markSpawned($job['id']);
            }
        } catch (Throwable $e) {
            return $this->responses->redirect(
                $basePath . '/admin/about?upgrade_error=' . rawurlencode(
                    $this->ui->get($user, 'admin.about.upgrade_failed', ['message' => $e->getMessage()]),
                ),
            );
        }

        return $this->responses->redirect($basePath . '/admin/about?upgrading=1');
    }

    public function upgradeStatus(ServerRequestInterface $request): ResponseInterface
    {
        $gate = $this->authorize($request);
        if ($gate instanceof ResponseInterface) {
            return $gate;
        }
        [$user, $site] = $gate;
        if (!$this->policy->can($user, $site, Permission::SETTINGS_MANAGE)) {
            return $this->responses->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        return $this->responses->json($this->upgradeStatusPayload($user));
    }

    public function upgradeRun(ServerRequestInterface $request): ResponseInterface
    {
        $gate = $this->authorize($request);
        if ($gate instanceof ResponseInterface) {
            return $gate;
        }
        [$user, $site] = $gate;
        if (!$this->policy->can($user, $site, Permission::SETTINGS_MANAGE)) {
            return $this->responses->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $progress = $this->upgradeProgress->read();
        if ($progress === null) {
            return $this->responses->json(['ok' => false, 'error' => 'no_job', 'progress' => null], 404);
        }
        if (in_array($progress['status'], ['done', 'failed'], true)) {
            return $this->responses->json($this->upgradeStatusPayload($user));
        }

        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        ignore_user_abort(true);

        $claimed = $this->upgradeProgress->claim($progress['id']);
        if ($claimed === null) {
            // Another worker (CLI) already claimed or finished.
            return $this->responses->json($this->upgradeStatusPayload($user));
        }

        try {
            $result = $this->cmsUpdater->upgradeFromCatalog(
                $claimed['cms'],
                $site->id,
                function (string $step, string $state, string $detail = ''): void {
                    $this->upgradeProgress->setStep($step, $state, $detail);
                },
            );
            $this->upgradeProgress->complete($result);
        } catch (Throwable $e) {
            $this->upgradeProgress->fail($e->getMessage());
        }

        return $this->responses->json($this->upgradeStatusPayload($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function upgradeStatusPayload(User $user): array
    {
        $progress = $this->upgradeProgress->read();
        if ($progress === null) {
            return ['ok' => true, 'progress' => null];
        }

        $labels = $this->upgradeStepLabels($user);
        $steps = [];
        foreach ($progress['steps'] as $step) {
            $steps[] = [
                'key' => $step['key'],
                'state' => $step['state'],
                'label' => $labels[$step['key']] ?? $step['key'],
            ];
        }

        $doneMessage = '';
        if ($progress['status'] === 'done') {
            $msgKey = version_compare(ltrim($progress['to'], 'vV'), ltrim($progress['from'], 'vV'), '<')
                ? 'admin.about.downgrade_ok'
                : 'admin.about.upgrade_ok';
            $doneMessage = $this->ui->get($user, $msgKey, [
                'from' => $progress['from'],
                'to' => $progress['to'],
                'stamp' => (string) ($progress['backupStamp'] ?? ''),
            ]);
        }

        return [
            'ok' => true,
            'progress' => [
                'id' => $progress['id'],
                'status' => $progress['status'],
                'from' => $progress['from'],
                'to' => $progress['to'],
                'current' => $progress['current'],
                'currentLabel' => $labels[$progress['current']]
                    ?? ($progress['current'] === 'queued'
                        ? $this->ui->get($user, 'admin.about.upgrade_step.queued')
                        : $progress['current']),
                'steps' => $steps,
                'message' => $progress['message'],
                'backupStamp' => $progress['backupStamp'],
                'error' => $progress['error'],
                'spawned' => !empty($progress['spawned']),
                'doneMessage' => $doneMessage,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function upgradeStepLabels(User $user): array
    {
        $out = [];
        foreach (CmsUpgradeProgress::STEP_KEYS as $key) {
            $out[$key] = $this->ui->get($user, 'admin.about.upgrade_step.' . $key);
        }
        $out['queued'] = $this->ui->get($user, 'admin.about.upgrade_step.queued');
        $out['done'] = $this->ui->get($user, 'admin.about.upgrade_step.done');

        return $out;
    }

    /**
     * @return list<array{version: string, phpRequirement: string, downloadUrl: string, changelogMd: string}>
     */
    private function olderCmsReleases(string $installed, bool $allowNetwork = true): array
    {
        $out = [];
        foreach ($this->marketplace->listCmsReleases($allowNetwork) as $row) {
            if (version_compare(ltrim($row['version'], 'vV'), ltrim($installed, 'vV'), '<')) {
                $out[] = $row;
            }
        }

        return $out;
    }

    private function needsUpdateRefresh(Site $site): bool
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

        return $this->marketplace->needsNetworkUpdateCheck($localVersions, [
            'install_id' => $site->id->value,
            'php' => PHP_VERSION,
            'db' => $this->databaseServerVersion(),
            'locale' => $site->defaultLocale,
        ], $localThemes);
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
