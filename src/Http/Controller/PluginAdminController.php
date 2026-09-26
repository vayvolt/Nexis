<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Audit\AuditLogger;
use Nexis\Auth\Permission;
use Nexis\Auth\PdoPermissionLookup;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Event\EventDispatcher;
use Nexis\Event\PluginToggled;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Plugin\MarketplaceClient;
use Nexis\Plugin\PluginCatalog;
use Nexis\Plugin\PluginDiscovery;
use Nexis\Plugin\PluginInstallStatus;
use Nexis\Plugin\PluginPackageInstaller;
use Nexis\Plugin\PluginUninstaller;
use Nexis\Plugin\SettingsSchemaRegistry;
use Nexis\Site\SiteRepository;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class PluginAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private PluginCatalog $catalog,
        private PluginDiscovery $discovery,
        private PluginPackageInstaller $installer,
        private PluginUninstaller $uninstaller,
        private AuditLogger $audit,
        private EventDispatcher $events,
        private PdoPermissionLookup $permissions,
        private AdminUi $ui,
        private SettingsSchemaRegistry $settingsSchema,
        private MarketplaceClient $marketplace,
        private PDO $pdo,
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
        $this->permissions->ensurePermissions([Permission::PLUGIN_INSTALL, Permission::PLUGIN_MANAGE]);
        $this->catalog->sync($this->discovery->discover());

        $notice = '';
        $noticeTone = 'success';
        if (RequestInput::query($request, 'enabled') === '1') {
            $plugin = RequestInput::query($request, 'plugin', '');
            $notice = $plugin !== ''
                ? $this->ui->get($user, 'admin.plugins.notice.enabled', ['plugin' => $plugin])
                : $this->ui->get($user, 'admin.plugins.notice.enabled_plain');
        } elseif (RequestInput::query($request, 'disabled') === '1') {
            $plugin = RequestInput::query($request, 'plugin', '');
            $notice = $plugin !== ''
                ? $this->ui->get($user, 'admin.plugins.notice.disabled', ['plugin' => $plugin])
                : $this->ui->get($user, 'admin.plugins.notice.disabled_plain');
        } elseif (RequestInput::query($request, 'saved') === '1') {
            $notice = $this->ui->get($user, 'admin.plugins.notice.saved');
        }
        if (RequestInput::query($request, 'installed') === '1') {
            $plugin = RequestInput::query($request, 'plugin', '');
            $notice = $plugin !== ''
                ? $this->ui->get($user, 'admin.plugins.notice.installed', ['plugin' => $plugin])
                : $this->ui->get($user, 'admin.plugins.notice.installed_plain');
            $noticeTone = 'info';
        } elseif (RequestInput::query($request, 'uninstalled') === '1') {
            $plugin = RequestInput::query($request, 'plugin', '');
            $notice = $plugin !== ''
                ? $this->ui->get($user, 'admin.plugins.notice.uninstalled', ['plugin' => $plugin])
                : $this->ui->get($user, 'admin.plugins.notice.uninstalled_plain');
        }

        $plugins = $this->catalog->listForSite($site->id);
        $marketplaceUpdates = [];
        $cmsUpdate = null;
        if ($this->marketplace->configured()) {
            $localVersions = [];
            foreach ($plugins as $row) {
                $key = (string) ($row['key'] ?? '');
                $version = (string) ($row['version'] ?? '');
                if ($key !== '' && $version !== '' && ($row['status'] ?? '') !== 'discovered') {
                    $localVersions[$key] = $version;
                }
            }
            try {
                $dbVersion = '';
                try {
                    $stmt = $this->pdo->query('SELECT VERSION()');
                    if ($stmt !== false) {
                        $dbVersion = (string) $stmt->fetchColumn();
                    }
                } catch (Throwable) {
                    $dbVersion = '';
                }
                $check = $this->marketplace->checkForUpdates($localVersions, [
                    'install_id' => $site->id->value,
                    'php' => PHP_VERSION,
                    'db' => $dbVersion,
                    'locale' => $site->defaultLocale,
                ]);
                $marketplaceUpdates = $check['plugins'];
                $cmsUpdate = $check['cms'];
            } catch (Throwable $e) {
                $this->logger?->warning('Marketplace update check failed', ['exception' => $e->getMessage()]);
            }
        }

        return $this->responses->html($this->views->render('admin.plugins.index', [
            'user' => $user,
            'site' => $site,
            'plugins' => $plugins,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'canInstall' => $this->policy->can($user, $site, Permission::PLUGIN_INSTALL),
            'notice' => $notice,
            'noticeTone' => $noticeTone,
            'error' => RequestInput::query($request, 'error'),
            'pluginsWithSettings' => array_fill_keys($this->settingsSchema->pluginIds(), true),
            'marketplaceConfigured' => $this->marketplace->configured(),
            'marketplaceUrl' => $this->marketplace->baseUrl(),
            'marketplaceUpdates' => $marketplaceUpdates,
            'cmsUpdate' => $cmsUpdate,
        ], 'admin.layout'));
    }

    public function marketplace(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        if (!$this->policy->can($user, $site, Permission::PLUGIN_INSTALL)
            && !$this->policy->can($user, $site, Permission::PLUGIN_MANAGE)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'plugin.install']), 403);
        }

        $q = trim(RequestInput::query($request, 'q', ''));
        $page = max(1, (int) RequestInput::query($request, 'page', '1'));
        $marketplaceError = RequestInput::query($request, 'error');
        $result = ['items' => [], 'total' => 0, 'page' => 1, 'perPage' => 20];
        if ($this->marketplace->configured() && $marketplaceError === '') {
            try {
                $result = $this->marketplace->search($q, $page);
            } catch (RuntimeException $e) {
                $this->logger?->warning('Marketplace browse failed', ['exception' => $e->getMessage()]);
                $marketplaceError = $this->ui->get($user, 'admin.plugins.marketplace.unreachable');
            }
        } elseif ($this->marketplace->configured() && $marketplaceError !== '') {
            try {
                $result = $this->marketplace->search($q, $page);
            } catch (RuntimeException) {
                // keep query error
            }
        }

        $installed = [];
        $installedVersions = [];
        foreach ($this->catalog->listForSite($site->id) as $row) {
            $key = (string) ($row['key'] ?? '');
            if ($key !== '') {
                $installed[$key] = (string) ($row['status'] ?? 'discovered');
                $installedVersions[$key] = (string) ($row['version'] ?? '');
            }
        }

        return $this->responses->html($this->views->render('admin.plugins.marketplace', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'canInstall' => $this->policy->can($user, $site, Permission::PLUGIN_INSTALL),
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
        if (!$this->policy->can($user, $site, Permission::PLUGIN_INSTALL)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'plugin.install']), 403);
        }
        if (!$this->marketplace->configured()) {
            return $this->responses->redirect($basePath . '/admin/plugins/marketplace?error=' . rawurlencode(
                $this->ui->get($user, 'admin.plugins.marketplace.not_configured'),
            ));
        }

        $slug = trim(RequestInput::string($request, 'plugin'));
        $overwrite = RequestInput::string($request, 'overwrite') === '1';
        if ($slug === '' || !str_contains($slug, '/')) {
            return $this->responses->redirect($basePath . '/admin/plugins/marketplace?error=' . rawurlencode(
                $this->ui->get($user, 'admin.plugins.marketplace.invalid_plugin'),
            ));
        }

        try {
            $detail = $this->marketplace->plugin($slug);
            if ($detail === null) {
                return $this->responses->redirect($basePath . '/admin/plugins/marketplace?error=' . rawurlencode(
                    $this->ui->get($user, 'admin.plugins.marketplace.not_found'),
                ));
            }
            $latest = is_array($detail['latest'] ?? null) ? $detail['latest'] : [];
            $version = (string) ($latest['version'] ?? '');
            $expectedSha = (string) ($latest['packageSha256'] ?? '');
            if ($version === '' || ($latest['downloadUrl'] ?? null) === null) {
                return $this->responses->redirect($basePath . '/admin/plugins/marketplace?error=' . rawurlencode(
                    $this->ui->get($user, 'admin.plugins.marketplace.no_package'),
                ));
            }

            $tmp = tempnam(sys_get_temp_dir(), 'nexis-mkt-');
            if ($tmp === false) {
                return $this->responses->redirect($basePath . '/admin/plugins/marketplace?error=' . rawurlencode(
                    $this->ui->get($user, 'admin.error.plugins_temp_failed'),
                ));
            }
            $zipPath = $tmp . '.zip';
            rename($tmp, $zipPath);

            try {
                $sha = $this->marketplace->downloadRelease($slug, $version, $zipPath);
                if ($expectedSha !== '' && !hash_equals($expectedSha, $sha)) {
                    throw new RuntimeException($this->ui->get($user, 'admin.plugins.marketplace.checksum_mismatch'));
                }
                $result = $this->installer->installFromZip($zipPath, $site->id, $overwrite);
                $this->marketplace->clearUpdateCheckCache();
                $this->audit->log('plugin.marketplace.installed', actorId: $user->id, context: [
                    'plugin' => $result['manifest']->id,
                    'version' => $result['manifest']->version,
                    'source' => 'marketplace',
                ]);

                return $this->responses->redirect(
                    $basePath . '/admin/plugins?installed=1&plugin=' . rawurlencode($result['manifest']->id),
                );
            } finally {
                if (is_file($zipPath)) {
                    @unlink($zipPath);
                }
            }
        } catch (Throwable $e) {
            $this->logger?->warning('Marketplace install failed', ['plugin' => $slug, 'exception' => $e->getMessage()]);

            return $this->responses->redirect(
                $basePath . '/admin/plugins/marketplace?error=' . rawurlencode($e->getMessage()),
            );
        }
    }

    public function upload(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        if (!$this->policy->can($user, $site, Permission::PLUGIN_INSTALL)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'plugin.install']), 403);
        }

        $files = $request->getUploadedFiles();
        $file = $files['package'] ?? null;
        if (!$file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            return $this->responses->redirect($basePath . '/admin/plugins?error=' . rawurlencode($this->ui->get($user, 'admin.error.plugins_zip_failed')));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'nexis-plugin-');
        if ($tmp === false) {
            return $this->responses->redirect($basePath . '/admin/plugins?error=' . rawurlencode($this->ui->get($user, 'admin.error.plugins_temp_failed')));
        }
        $zipPath = $tmp . '.zip';
        rename($tmp, $zipPath);

        try {
            $file->moveTo($zipPath);
            $overwrite = RequestInput::string($request, 'overwrite') === '1';
            $result = $this->installer->installFromZip($zipPath, $site->id, $overwrite);
            $this->audit->log(
                'plugin.install',
                $site->id,
                $user->id,
                'plugin',
                null,
                [
                    'plugin' => $result['manifest']->id,
                    'version' => $result['manifest']->version,
                    'overwritten' => $result['overwritten'],
                ],
            );

            return $this->responses->redirect(
                $basePath . '/admin/plugins?installed=1&plugin=' . rawurlencode($result['manifest']->id),
            );
        } catch (Throwable $e) {
            return $this->responses->redirect(
                $basePath . '/admin/plugins?error=' . rawurlencode($e->getMessage()),
            );
        } finally {
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
        }
    }

    public function enable(ServerRequestInterface $request): ResponseInterface
    {
        return $this->toggle($request, PluginInstallStatus::Enabled);
    }

    public function disable(ServerRequestInterface $request): ResponseInterface
    {
        return $this->toggle($request, PluginInstallStatus::Disabled);
    }

    public function uninstall(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        if (!$this->policy->can($user, $site, Permission::PLUGIN_MANAGE)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'plugin.manage']), 403);
        }
        $key = RequestInput::string($request, 'plugin');
        if ($key === '') {
            return $this->responses->redirect($basePath . '/admin/plugins?error=' . rawurlencode($this->ui->get($user, 'admin.error.plugins_missing')));
        }
        if (RequestInput::string($request, 'confirm') !== '1') {
            return $this->responses->redirect(
                $basePath . '/admin/plugins?error=' . rawurlencode($this->ui->get($user, 'admin.error.plugins_uninstall_confirm')),
            );
        }

        try {
            $this->catalog->sync($this->discovery->discover());
            $this->uninstaller->uninstall($site->id, $key, $user->id);
            $this->audit->log(
                'plugin.uninstall',
                $site->id,
                $user->id,
                'plugin',
                null,
                ['plugin' => $key],
            );

            return $this->responses->redirect(
                $basePath . '/admin/plugins?uninstalled=1&plugin=' . rawurlencode($key),
            );
        } catch (Throwable $e) {
            return $this->responses->redirect(
                $basePath . '/admin/plugins?error=' . rawurlencode($e->getMessage()),
            );
        }
    }

    private function toggle(ServerRequestInterface $request, PluginInstallStatus $status): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        if (!$this->policy->can($user, $site, Permission::PLUGIN_MANAGE)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'plugin.manage']), 403);
        }
        $key = RequestInput::string($request, 'plugin');
        if ($key === '' || !str_contains($key, '/')) {
            return $this->responses->redirect(
                $basePath . '/admin/plugins?error=' . rawurlencode($this->ui->get($user, 'admin.plugins.error.invalid')),
            );
        }
        $this->catalog->sync($this->discovery->discover());
        if ($status === PluginInstallStatus::Enabled) {
            $missing = $this->missingRequiredPlugins($site->id, $key);
            if ($missing !== []) {
                $message = $this->ui->get($user, 'admin.plugins.error.requires', [
                    'plugin' => $key,
                    'requires' => implode(', ', $missing),
                ]);

                return $this->responses->redirect(
                    $basePath . '/admin/plugins?error=' . rawurlencode($message),
                );
            }
        }
        $this->catalog->ensureInstallation($site->id, $key, PluginInstallStatus::Installed);
        $this->catalog->setStatus($site->id, $key, $status);
        $this->audit->log(
            $status === PluginInstallStatus::Enabled ? 'plugin.enable' : 'plugin.disable',
            $site->id,
            $user->id,
            'plugin',
            null,
            ['plugin' => $key],
        );
        $this->events->dispatch(new PluginToggled(
            $site->id,
            $key,
            $status === PluginInstallStatus::Enabled,
            $user->id,
        ));

        $flag = $status === PluginInstallStatus::Enabled ? 'enabled=1' : 'disabled=1';

        return $this->responses->redirect(
            $basePath . '/admin/plugins?' . $flag . '&plugin=' . rawurlencode($key),
        );
    }

    /**
     * @return array{User, \Nexis\Site\Site, string}|ResponseInterface
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

    /**
     * @return list<string>
     */
    private function missingRequiredPlugins(\Nexis\Site\SiteId $siteId, string $pluginKey): array
    {
        $manifest = null;
        foreach ($this->discovery->discover() as $item) {
            if ($item->id === $pluginKey) {
                $manifest = $item;
                break;
            }
        }
        if ($manifest === null || $manifest->requiredPlugins === []) {
            return [];
        }
        $enabled = array_fill_keys($this->catalog->enabledKeys($siteId), true);
        $missing = [];
        foreach ($manifest->requiredPlugins as $req) {
            if (!isset($enabled[$req])) {
                $missing[] = $req;
            }
        }

        return $missing;
    }
}
