<?php

declare(strict_types=1);

namespace Nexis\Install;

use Nexis\Auth\PasswordHasher;
use Nexis\Auth\PdoPermissionLookup;
use Nexis\Auth\SystemRoleSeeder;
use Nexis\Http\RequestSecurity;
use Nexis\Infrastructure\Database\Migrator;
use Nexis\Kernel\Nexis;
use Nexis\Plugin\ManifestLoader;
use Nexis\Plugin\PluginCatalog;
use Nexis\Plugin\PluginDiscovery;
use Nexis\Plugin\PluginInstallStatus;
use Nexis\Site\SiteId;
use Nexis\Support\SystemClock;
use Nexis\Support\Uuid;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Standalone web installer (runs before full Application boot when needed).
 */
final class WebInstaller
{
    private InstallUi $ui;

    public function __construct(
        private string $rootPath,
        private InstallDetector $detector,
    ) {
        $this->rootPath = rtrim($rootPath, '\\/');
        $this->ui = InstallUi::forLocale('de', $this->rootPath);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $basePath = (string) $request->getAttribute('base_path', '');
        $path = (string) $request->getAttribute('path', '/');

        $this->ensureSession();
        $this->ui = InstallUi::forLocale(InstallUi::resolveLocale($request), $this->rootPath);

        if (!$this->detector->needsInstall()) {
            return $this->html($this->renderAlreadyInstalled($basePath), 403);
        }

        if ($path !== '/install' && !str_starts_with($path, '/install/')) {
            return new Response(302, ['Location' => $basePath . '/install']);
        }

        if (strtoupper($request->getMethod()) === 'POST') {
            $limited = $this->assertInstallRateLimit($request, $basePath);
            if ($limited !== null) {
                return $limited;
            }

            return $this->submit($request, $basePath);
        }

        return $this->html($this->renderForm($basePath, $this->defaults($request), '', $this->requirements()));
    }

    private function submit(ServerRequestInterface $request, string $basePath): ResponseInterface
    {
        $body = $request->getParsedBody();
        $data = is_array($body) ? $body : [];
        $csrf = (string) ($data['_csrf'] ?? '');
        if (!$this->verifyCsrf($csrf)) {
            return $this->html($this->renderForm($basePath, $data, $this->ui->get('error.csrf'), $this->requirements()), 422);
        }

        $reqs = $this->requirements();
        foreach ($reqs as $req) {
            if (!$req['ok']) {
                return $this->html($this->renderForm($basePath, $data, $this->ui->get('error.requirements'), $reqs), 422);
            }
        }

        $checkDbOnly = ((string) ($data['action'] ?? '')) === 'check_db';
        $resetDatabase = !empty($data['reset_database']);

        $siteName = trim((string) ($data['site_name'] ?? ''));
        $domain = trim((string) ($data['primary_domain'] ?? ''));
        $adminName = trim((string) ($data['admin_name'] ?? ''));
        $adminEmail = trim((string) ($data['admin_email'] ?? ''));
        $adminPassword = (string) ($data['admin_password'] ?? '');
        $dbHost = trim((string) ($data['db_host'] ?? '127.0.0.1'));
        $dbPort = trim((string) ($data['db_port'] ?? '3306'));
        $dbName = trim((string) ($data['db_database'] ?? 'nexis'));
        $dbUser = trim((string) ($data['db_username'] ?? 'root'));
        $dbPass = (string) ($data['db_password'] ?? '');
        $appUrl = trim((string) ($data['app_url'] ?? ''));
        $enablePlugins = !empty($data['enable_plugins']);
        $defaultLocale = strtolower(trim((string) ($data['default_locale'] ?? 'de')));
        if (!in_array($defaultLocale, ['de', 'en'], true)) {
            $defaultLocale = 'de';
        }

        if ($dbName === '' || !preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
            return $this->html($this->renderForm($basePath, $data, $this->ui->get('error.db_name'), $reqs), 422);
        }

        $preflight = new DatabasePreflight();
        $check = $preflight->verify($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
        if (!$check->ok) {
            return $this->html(
                $this->renderForm($basePath, $data, $this->preflightErrorMessage($check), $reqs),
                422,
            );
        }

        try {
            $existing = $preflight->inspectExisting(
                $preflight->connect($dbHost, $dbPort, $dbName, $dbUser, $dbPass),
            );
        } catch (Throwable $e) {
            return $this->html(
                $this->renderForm(
                    $basePath,
                    $data,
                    $this->ui->get('error.db_connection', ['message' => $e->getMessage()]),
                    $reqs,
                ),
                422,
            );
        }

        if ($checkDbOnly) {
            $notice = $this->ui->get('db_check.ok');
            if (!$existing->isEmpty()) {
                $notice = $this->ui->get('db_check.ok_existing', [
                    'tables' => (string) $existing->tableCount,
                    'sites' => (string) $existing->siteCount,
                ]);
            }

            return $this->html(
                $this->renderForm($basePath, $data, '', $reqs, $notice, $existing),
            );
        }

        if ($siteName === '' || $domain === '' || $adminName === '' || $adminEmail === '' || $adminPassword === '') {
            return $this->html($this->renderForm($basePath, $data, $this->ui->get('error.required'), $reqs, '', $existing), 422);
        }
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->html($this->renderForm($basePath, $data, $this->ui->get('error.email'), $reqs, '', $existing), 422);
        }
        if (strlen($adminPassword) < 8) {
            return $this->html($this->renderForm($basePath, $data, $this->ui->get('error.password'), $reqs, '', $existing), 422);
        }

        if (!$existing->isEmpty() && !$resetDatabase) {
            return $this->html(
                $this->renderForm(
                    $basePath,
                    $data,
                    $this->ui->get('error.db_not_empty', [
                        'tables' => (string) $existing->tableCount,
                        'sites' => (string) $existing->siteCount,
                    ]),
                    $reqs,
                    '',
                    $existing,
                ),
                422,
            );
        }

        try {
            // Never leave .env / installed from a failed attempt: clear leftovers first,
            // write them only after DB + content succeed.
            $this->discardInstallArtifacts();

            $pdo = $preflight->connect($dbHost, $dbPort, $dbName, $dbUser, $dbPass);

            if ($resetDatabase && !$existing->isEmpty()) {
                $preflight->wipeAllTables($pdo);
            }

            $migrator = new Migrator(
                $pdo,
                $this->rootPath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'core_schema.sql',
            );
            $migrator->migrate();

            $siteIds = $this->provision($pdo, $siteName, $domain, $adminName, $adminEmail, $adminPassword, $defaultLocale);
            if ($enablePlugins) {
                $this->enableFirstPartyPlugins($pdo, $siteIds['siteId']);
            }

            $appUrl = $appUrl !== '' ? $appUrl : $this->guessAppUrl($request, $basePath);
            $isLocal = RequestSecurity::isLocalHost($request->getUri()->getHost())
                || RequestSecurity::isLocalHost($domain);
            $this->writeEnv([
                'APP_ENV' => $isLocal ? 'local' : 'production',
                'APP_DEBUG' => $isLocal ? 'true' : 'false',
                'APP_URL' => $appUrl,
                'APP_KEY' => bin2hex(random_bytes(32)),
                'TRUSTED_PROXIES' => '',
                'PLUGIN_TRUST_PUBLIC_KEY' => '',
                'DB_HOST' => $dbHost,
                'DB_PORT' => $dbPort !== '' ? $dbPort : '3306',
                'DB_DATABASE' => $dbName,
                'DB_USERNAME' => $dbUser,
                'DB_PASSWORD' => $dbPass,
                'MAIL_TRANSPORT' => 'log',
                'MAIL_HOST' => '127.0.0.1',
                'MAIL_PORT' => '587',
                'MAIL_ENCRYPTION' => 'tls',
                'MAIL_USERNAME' => '',
                'MAIL_PASSWORD' => '',
                'MAIL_FROM_ADDRESS' => 'noreply@' . $domain,
                'MAIL_FROM_NAME' => $siteName,
            ]);

            try {
                (new InstallDefaultContent())->seed(
                    $this->rootPath,
                    $siteIds,
                    $siteName,
                    $adminEmail,
                    $basePath,
                    $enablePlugins,
                );
                $this->detector->markInstalled('web');
            } catch (Throwable $seedError) {
                $this->discardInstallArtifacts();
                throw $seedError;
            }

            $this->clearCsrf();

            return new Response(302, ['Location' => $basePath . '/admin/login?installed=1&lang=' . rawurlencode($this->ui->locale)]);
        } catch (Throwable $e) {
            $this->discardInstallArtifacts();

            $existingAfter = $existing;
            try {
                $existingAfter = $preflight->inspectExisting(
                    $preflight->connect($dbHost, $dbPort, $dbName, $dbUser, $dbPass),
                );
            } catch (Throwable) {
            }

            return $this->html(
                $this->renderForm(
                    $basePath,
                    $data,
                    $this->ui->get('error.failed', ['message' => $e->getMessage()]),
                    $this->requirements(),
                    '',
                    $existingAfter,
                ),
                500,
            );
        }
    }

    private function preflightErrorMessage(DatabasePreflightResult $result): string
    {
        $replace = ['message' => $result->detail !== '' ? $result->detail : '—'];

        return match ($result->code) {
            'invalid_name' => $this->ui->get('error.db_name'),
            'create_database' => $this->ui->get('error.db_create', $replace),
            'select_database' => $this->ui->get('error.db_select', $replace),
            'privileges' => $this->ui->get('error.db_privileges', $replace),
            default => $this->ui->get('error.db_connection', $replace),
        };
    }

    private function assertInstallRateLimit(ServerRequestInterface $request, string $basePath): ?ResponseInterface
    {
        $dir = $this->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'rate-limit';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $server = $request->getServerParams();
        $ip = is_string($server['REMOTE_ADDR'] ?? null) && $server['REMOTE_ADDR'] !== ''
            ? (string) $server['REMOTE_ADDR']
            : '0.0.0.0';
        $bucket = $dir . DIRECTORY_SEPARATOR . hash('sha256', $ip . '|/install') . '.json';
        $now = time();
        $window = 600;
        $max = 10;
        $hits = [];
        if (is_file($bucket)) {
            $raw = file_get_contents($bucket);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                foreach ($decoded as $ts) {
                    if (is_int($ts) && $ts > $now - $window) {
                        $hits[] = $ts;
                    }
                }
            }
        }
        if (count($hits) >= $max) {
            return $this->html(
                $this->renderForm($basePath, $this->defaults($request), $this->ui->get('error.rate_limit'), $this->requirements()),
                429,
            );
        }
        $hits[] = $now;
        file_put_contents($bucket, json_encode($hits, JSON_THROW_ON_ERROR));

        return null;
    }

    /**
     * @param array<string, string> $values
     */
    private function writeEnv(array $values): void
    {
        $lines = [];
        foreach ($values as $key => $value) {
            $escaped = $value;
            if ($escaped === '' || preg_match('/[\s#"\']/', $escaped) === 1) {
                $escaped = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
            }
            $lines[] = $key . '=' . $escaped;
        }
        $target = $this->rootPath . DIRECTORY_SEPARATOR . '.env';
        if (file_put_contents($target, implode("\n", $lines) . "\n") === false) {
            throw new \RuntimeException($this->ui->get('error.env_write'));
        }
    }

    /**
     * Removes .env and the install lock so a failed/partial install cannot look finished.
     */
    private function discardInstallArtifacts(): void
    {
        $env = $this->rootPath . DIRECTORY_SEPARATOR . '.env';
        if (is_file($env)) {
            @unlink($env);
        }
        $lock = $this->detector->lockPath();
        if (is_file($lock)) {
            @unlink($lock);
        }
    }

    /**
     * @return array{
     *   siteId: string,
     *   adminId: string,
     *   homeId: string,
     *   imprintId: string,
     *   privacyId: string,
     *   homeEnId: string,
     *   imprintEnId: string,
     *   privacyEnId: string
     * }
     */
    private function provision(
        \PDO $pdo,
        string $siteName,
        string $domain,
        string $adminName,
        string $adminEmail,
        string $adminPassword,
        string $defaultLocale = 'de',
    ): array {
        $now = gmdate('Y-m-d H:i:s.v');
        $hasher = new PasswordHasher();
        $tenantId = Uuid::v7();
        $siteId = Uuid::v7();
        $adminId = Uuid::v7();
        $homeId = Uuid::v7();
        $imprintId = Uuid::v7();
        $privacyId = Uuid::v7();
        $homeEnId = Uuid::v7();
        $imprintEnId = Uuid::v7();
        $privacyEnId = Uuid::v7();
        $homeGroup = Uuid::v7();
        $imprintGroup = Uuid::v7();
        $privacyGroup = Uuid::v7();
        if (!in_array($defaultLocale, ['de', 'en'], true)) {
            $defaultLocale = 'de';
        }

        $pdo->beginTransaction();
        try {
            $this->insert($pdo, 'tenants', [
                'id' => $tenantId,
                'name' => $siteName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insert($pdo, 'sites', [
                'id' => $siteId,
                'tenant_id' => $tenantId,
                'name' => $siteName,
                'primary_domain' => $domain,
                'default_locale' => $defaultLocale,
                'locale_url_strategy' => 'prefix',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insert($pdo, 'site_domains', [
                'id' => Uuid::v7(),
                'site_id' => $siteId,
                'host' => $domain,
                'is_primary' => 1,
                'created_at' => $now,
            ]);
            $this->insert($pdo, 'site_locales', [
                'id' => Uuid::v7(),
                'site_id' => $siteId,
                'locale' => 'de',
                'label' => 'Deutsch',
                'url_prefix' => 'de',
                'hreflang' => 'de',
                'is_default' => $defaultLocale === 'de' ? 1 : 0,
                'enabled' => 1,
            ]);
            $this->insert($pdo, 'site_locales', [
                'id' => Uuid::v7(),
                'site_id' => $siteId,
                'locale' => 'en',
                'label' => 'English',
                'url_prefix' => 'en',
                'hreflang' => 'en',
                'is_default' => $defaultLocale === 'en' ? 1 : 0,
                'enabled' => 1,
            ]);
            $this->insert($pdo, 'users', [
                'id' => $adminId,
                'email' => $adminEmail,
                'password_hash' => $hasher->hash($adminPassword),
                'display_name' => $adminName,
                'is_platform_admin' => 1,
                'ui_locale' => $defaultLocale,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $permissions = new PdoPermissionLookup($pdo);
            $seeder = new SystemRoleSeeder($pdo, $permissions);
            $seeder->ensureForSite(new SiteId($siteId));
            $adminRoleId = $this->roleIdBySlug($pdo, $siteId, 'admin');
            if ($adminRoleId === null) {
                throw new \RuntimeException('System-Rolle admin fehlt nach dem Seeding.');
            }

            $this->insert($pdo, 'site_memberships', [
                'id' => Uuid::v7(),
                'site_id' => $siteId,
                'user_id' => $adminId,
                'role_id' => $adminRoleId,
                'created_at' => $now,
            ]);

            $this->insert($pdo, 'pages', [
                'id' => $homeId,
                'site_id' => $siteId,
                'translation_group_id' => $homeGroup,
                'type' => 'page',
                'slug' => 'home',
                'path' => '/',
                'locale' => 'de',
                'title' => 'Startseite',
                'meta_title' => $siteName,
                'meta_description' => 'Platzhalter-Startseite — zeigen, was mit Nexis möglich ist. Bitte Inhalte unter Admin → Seiten anpassen.',
                'status' => 'draft',
                'robots' => 'index,follow',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insert($pdo, 'pages', [
                'id' => $homeEnId,
                'site_id' => $siteId,
                'translation_group_id' => $homeGroup,
                'type' => 'page',
                'slug' => 'home',
                'path' => '/',
                'locale' => 'en',
                'title' => 'Home',
                'meta_title' => $siteName,
                'meta_description' => 'Placeholder homepage — shows what Nexis can do. Edit content under Admin → Pages.',
                'status' => 'draft',
                'robots' => 'index,follow',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insert($pdo, 'pages', [
                'id' => $imprintId,
                'site_id' => $siteId,
                'translation_group_id' => $imprintGroup,
                'type' => 'page',
                'slug' => 'impressum',
                'path' => '/impressum',
                'locale' => 'de',
                'title' => 'Impressum',
                'meta_title' => 'Impressum',
                'meta_description' => 'Rechtliche Angaben',
                'status' => 'draft',
                'robots' => 'index,follow',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insert($pdo, 'pages', [
                'id' => $imprintEnId,
                'site_id' => $siteId,
                'translation_group_id' => $imprintGroup,
                'type' => 'page',
                'slug' => 'imprint',
                'path' => '/imprint',
                'locale' => 'en',
                'title' => 'Legal notice',
                'meta_title' => 'Legal notice',
                'meta_description' => 'Legal information',
                'status' => 'draft',
                'robots' => 'index,follow',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insert($pdo, 'pages', [
                'id' => $privacyId,
                'site_id' => $siteId,
                'translation_group_id' => $privacyGroup,
                'type' => 'page',
                'slug' => 'datenschutz',
                'path' => '/datenschutz',
                'locale' => 'de',
                'title' => 'Datenschutzerklärung',
                'meta_title' => 'Datenschutzerklärung',
                'meta_description' => 'Informationen zur Verarbeitung personenbezogener Daten',
                'status' => 'draft',
                'robots' => 'index,follow',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insert($pdo, 'pages', [
                'id' => $privacyEnId,
                'site_id' => $siteId,
                'translation_group_id' => $privacyGroup,
                'type' => 'page',
                'slug' => 'privacy',
                'path' => '/privacy',
                'locale' => 'en',
                'title' => 'Privacy policy',
                'meta_title' => 'Privacy policy',
                'meta_description' => 'Information on the processing of personal data',
                'status' => 'draft',
                'robots' => 'index,follow',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return [
            'siteId' => $siteId,
            'adminId' => $adminId,
            'homeId' => $homeId,
            'imprintId' => $imprintId,
            'privacyId' => $privacyId,
            'homeEnId' => $homeEnId,
            'imprintEnId' => $imprintEnId,
            'privacyEnId' => $privacyEnId,
        ];
    }

    private function enableFirstPartyPlugins(\PDO $pdo, string $siteId): void
    {
        $clock = new SystemClock();
        $catalog = new PluginCatalog($pdo, $clock);
        $discovery = new PluginDiscovery(
            $this->rootPath . DIRECTORY_SEPARATOR . 'plugins',
            new ManifestLoader(),
        );
        $manifests = $discovery->discover();
        $catalog->sync($manifests);
        $site = new SiteId($siteId);
        $available = [];
        foreach ($manifests as $manifest) {
            $available[$manifest->id] = true;
        }
        foreach (['nexis/forms', 'nexis/redirects', 'nexis/consent', 'nexis/blog', 'nexis/catalog', 'nexis/workshop'] as $key) {
            if (!isset($available[$key])) {
                continue;
            }
            $catalog->ensureInstallation($site, $key, PluginInstallStatus::Installed);
            $catalog->setStatus($site, $key, PluginInstallStatus::Enabled);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function insert(\PDO $pdo, string $table, array $row): void
    {
        $cols = array_keys($row);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $cols);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        foreach ($row as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
    }

    private function roleIdBySlug(\PDO $pdo, string $siteId, string $slug): ?string
    {
        $stmt = $pdo->prepare(
            'SELECT id FROM roles WHERE site_id = :site_id AND slug = :slug LIMIT 1',
        );
        $stmt->execute(['site_id' => $siteId, 'slug' => $slug]);
        $id = $stmt->fetchColumn();

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @return list<array{label: string, ok: bool}>
     */
    private function requirements(): array
    {
        $storage = $this->rootPath . DIRECTORY_SEPARATOR . 'storage';
        $env = $this->rootPath . DIRECTORY_SEPARATOR . '.env';
        $envWritable = !is_file($env) ? is_writable($this->rootPath) : is_writable($env);

        $phpOk = version_compare(PHP_VERSION, '8.4.0', '>=');

        return [
            [
                'label' => $this->ui->get('req.php', ['version' => PHP_VERSION]),
                'ok' => $phpOk,
            ],
            ['label' => $this->ui->get('req.ext_intl'), 'ok' => extension_loaded('intl')],
            ['label' => $this->ui->get('req.ext_pdo_mysql'), 'ok' => extension_loaded('pdo_mysql')],
            ['label' => $this->ui->get('req.ext_gd'), 'ok' => extension_loaded('gd')],
            ['label' => $this->ui->get('req.ext_zip'), 'ok' => extension_loaded('zip')],
            ['label' => $this->ui->get('req.ext_sodium'), 'ok' => extension_loaded('sodium')],
            ['label' => $this->ui->get('req.ext_curl'), 'ok' => extension_loaded('curl')],
            ['label' => $this->ui->get('req.storage_writable'), 'ok' => is_dir($storage) && is_writable($storage)],
            ['label' => $this->ui->get('req.env_writable'), 'ok' => $envWritable],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(ServerRequestInterface $request): array
    {
        $host = $request->getUri()->getHost() ?: 'localhost';
        $basePath = (string) $request->getAttribute('base_path', '');

        return [
            'site_name' => $this->ui->get('default.site_name'),
            'primary_domain' => $host,
            'admin_name' => 'Admin',
            'admin_email' => 'admin@example.com',
            'admin_password' => '',
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_database' => 'nexis',
            'db_username' => 'root',
            'db_password' => '',
            'app_url' => $this->guessAppUrl($request, $basePath),
            'enable_plugins' => '1',
            'reset_database' => '',
            'default_locale' => $this->ui->locale,
        ];
    }

    private function guessAppUrl(ServerRequestInterface $request, string $basePath): string
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme() !== '' ? $uri->getScheme() : 'http';
        $host = $uri->getHost() !== '' ? $uri->getHost() : 'localhost';
        $port = $uri->getPort();
        $origin = $scheme . '://' . $host;
        if ($port !== null && !in_array($port, [80, 443], true)) {
            $origin .= ':' . $port;
        }

        return $origin . $basePath;
    }

    private function csrfToken(): string
    {
        $this->ensureSession();
        if (empty($_SESSION['_nexis_install_csrf'])) {
            $_SESSION['_nexis_install_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['_nexis_install_csrf'];
    }

    private function verifyCsrf(string $token): bool
    {
        $this->ensureSession();
        $expected = (string) ($_SESSION['_nexis_install_csrf'] ?? '');

        return $expected !== '' && hash_equals($expected, $token);
    }

    private function clearCsrf(): void
    {
        $this->ensureSession();
        unset($_SESSION['_nexis_install_csrf']);
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $path = $this->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        session_save_path($path);
        session_name('nexis_install');
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => $https,
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<array{label: string, ok: bool}> $requirements
     */
    private function renderForm(
        string $basePath,
        array $data,
        string $error,
        array $requirements,
        string $notice = '',
        ?DatabaseExistingContent $existing = null,
    ): string {
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $t = fn (string $key, array $replace = []): string => $this->ui->get($key, $replace);
        $csrf = $this->csrfToken();
        $val = static fn (string $key) => $e($data[$key] ?? '');
        $lang = $this->ui->locale;

        $reqRows = '';
        foreach ($requirements as $req) {
            $badge = $req['ok']
                ? '<span class="ok">' . $e($t('req.ok')) . '</span>'
                : '<span class="bad">' . $e($t('req.missing')) . '</span>';
            $reqRows .= '<li>' . $e($req['label']) . ' ' . $badge . '</li>';
        }

        $errorHtml = $error !== '' ? '<p class="error" role="alert">' . $e($error) . '</p>' : '';
        $noticeHtml = $notice !== '' ? '<p class="notice" role="status">' . $e($notice) . '</p>' : '';
        $pluginsChecked = !empty($data['enable_plugins']) ? ' checked' : '';
        $resetChecked = !empty($data['reset_database']) ? ' checked' : '';
        $showReset = $existing !== null && !$existing->isEmpty();
        $resetBlock = '';
        if ($showReset) {
            $resetTitle = $e($t('reset.title'));
            $resetWarn = $e($t('reset.warn', [
                'tables' => (string) $existing->tableCount,
                'sites' => (string) $existing->siteCount,
                'database' => (string) ($data['db_database'] ?? 'nexis'),
            ]));
            $resetLabel = $e($t('reset.label'));
            $resetHint = $e($t('reset.hint'));
            $resetBlock = <<<HTML
    <div class="reset-box">
      <strong>{$resetTitle}</strong>
      <p>{$resetWarn}</p>
      <label class="check danger">
        <input type="checkbox" name="reset_database" value="1"{$resetChecked}>
        <span>{$resetLabel}<small>{$resetHint}</small></span>
      </label>
    </div>
HTML;
        }
        $defaultLocale = (string) ($data['default_locale'] ?? $lang);
        $deSelected = $defaultLocale === 'de' ? ' selected' : '';
        $enSelected = $defaultLocale === 'en' ? ' selected' : '';
        $vendorUrl = $e(Nexis::VENDOR_URL);
        $attribution = $e(Nexis::ATTRIBUTION);
        $lede = $t('lede', [
            'vendor_url' => Nexis::VENDOR_URL,
            'vendor' => Nexis::VENDOR,
        ]);
        $lede = str_replace(
            [Nexis::VENDOR_URL, Nexis::VENDOR],
            [$e(Nexis::VENDOR_URL), $e(Nexis::VENDOR)],
            $lede,
        );
        $langSwitch = $this->renderLangSwitch($basePath, $e);

        $title = $e($t('title'));
        $requirementsLabel = $e($t('requirements'));
        $sectionWebsite = $e($t('section.website'));
        $sectionAdmin = $e($t('section.admin'));
        $sectionDatabase = $e($t('section.database'));
        $fieldSiteName = $e($t('field.site_name'));
        $fieldSiteNamePh = $e($t('field.site_name_ph'));
        $fieldPrimaryDomain = $e($t('field.primary_domain'));
        $fieldPrimaryDomainHint = $t('field.primary_domain_hint');
        $fieldAppUrl = $e($t('field.app_url'));
        $fieldAppUrlHint = $e($t('field.app_url_hint'));
        $fieldDefaultLocale = $e($t('field.default_locale'));
        $fieldDefaultLocaleHint = $e($t('field.default_locale_hint'));
        $localeDe = $e($t('locale.de'));
        $localeEn = $e($t('locale.en'));
        $fieldAdminName = $e($t('field.admin_name'));
        $fieldAdminEmail = $e($t('field.admin_email'));
        $fieldAdminPassword = $e($t('field.admin_password'));
        $fieldAdminPasswordPh = $e($t('field.admin_password_ph'));
        $fieldAdminPasswordHint = $e($t('field.admin_password_hint'));
        $fieldDbHost = $e($t('field.db_host'));
        $fieldDbPort = $e($t('field.db_port'));
        $fieldDbDatabase = $e($t('field.db_database'));
        $fieldDbDatabaseHint = $e($t('field.db_database_hint'));
        $fieldDbUsername = $e($t('field.db_username'));
        $fieldDbPassword = $e($t('field.db_password'));
        $fieldDbPasswordPh = $e($t('field.db_password_ph'));
        $plugins = $e($t('plugins'));
        $pluginsHint = $e($t('plugins_hint'));
        $submit = $e($t('submit'));
        $submitHint = $t('submit_hint');
        $checkDb = $e($t('db_check.submit'));

        return <<<HTML
<!DOCTYPE html>
<html lang="{$e($lang)}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<link rel="icon" type="image/svg+xml" href="{$e(Nexis::brandUrl($basePath, Nexis::BRAND_ICON))}">
<style>
:root {
  --ink: #1c1917;
  --muted: #78716c;
  --line: #e7e5e4;
  --bg: #faf8f5;
  --card: #fff;
  --focus: #0f766e;
  --danger: #991b1b;
  --danger-bg: #fef2f2;
  --ok: #166534;
  --ok-bg: #f0fdf4;
  --warn: #92400e;
  --warn-bg: #fffbeb;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  color: var(--ink);
  font-family: "Segoe UI", system-ui, sans-serif;
  background:
    radial-gradient(ellipse 80% 50% at 10% -10%, #dbeafe 0%, transparent 55%),
    radial-gradient(ellipse 70% 40% at 100% 0%, #fde68a55 0%, transparent 50%),
    var(--bg);
}
.wrap { max-width: 42rem; margin: 2rem auto; padding: 0 1rem 3rem; }
.topbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin: 0 0 1rem; }
.install-brand { margin: 0; }
.install-brand img { height: 2.75rem; width: auto; display: block; }
.lang-switch { display: flex; gap: .35rem; flex-wrap: wrap; }
.lang-switch a {
  font-size: .82rem; font-weight: 650; text-decoration: none; color: var(--muted);
  padding: .35rem .55rem; border-radius: 6px; border: 1px solid transparent;
}
.lang-switch a:hover { color: var(--ink); border-color: var(--line); background: #fff; }
.lang-switch a[aria-current="true"] {
  color: var(--ink); border-color: var(--line); background: #fff;
}
h1 { font-family: Georgia, "Times New Roman", serif; font-size: clamp(1.75rem, 4vw, 2.25rem); margin: 0 0 .35rem; letter-spacing: -.02em; }
.lede { color: var(--muted); margin: 0 0 1.25rem; line-height: 1.45; }
.lede a { color: var(--focus); }
.card {
  background: var(--card);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 1.25rem 1.35rem;
  margin: 0 0 1rem;
  box-shadow: 0 1px 0 rgba(28,25,23,.04);
}
.card h2 {
  margin: 0 0 1rem;
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  color: var(--muted);
}
.reqs { list-style: none; margin: 0; padding: 0; display: grid; gap: .4rem; }
.reqs li {
  display: flex; justify-content: space-between; align-items: center; gap: 1rem;
  padding: .45rem .65rem; border-radius: 8px; background: #f5f5f4; font-size: .92rem;
}
.ok, .bad { font-weight: 700; font-size: .78rem; }
.ok { color: var(--ok); }
.bad { color: var(--danger); }
.grid { display: grid; gap: .9rem; }
.grid-2 { grid-template-columns: 1fr 1fr; }
.field { display: flex; flex-direction: column; gap: .35rem; min-width: 0; }
.field-span { grid-column: 1 / -1; }
label { font-size: .88rem; font-weight: 600; color: var(--ink); }
.hint { margin: 0; font-size: .8rem; color: var(--muted); line-height: 1.35; }
input[type=text],
input[type=email],
input[type=password],
input[type=url],
input[type=number],
select {
  width: 100%;
  padding: .7rem .8rem;
  border: 1px solid #d6d3d1;
  border-radius: 8px;
  font: inherit;
  font-size: 1rem;
  color: var(--ink);
  background: #fff;
  transition: border-color .15s, box-shadow .15s;
}
input::placeholder { color: #a8a29e; }
input:hover, select:hover { border-color: #a8a29e; }
input:focus, select:focus {
  outline: none;
  border-color: var(--focus);
  box-shadow: 0 0 0 3px rgba(15, 118, 110, .18);
}
input:user-invalid:not(:focus) { border-color: #f87171; }
.check {
  display: flex; align-items: flex-start; gap: .65rem;
  margin: .25rem 0 0; padding: .85rem .9rem;
  border: 1px solid var(--line); border-radius: 8px; background: #fafaf9;
  cursor: pointer; font-weight: 500; font-size: .92rem; line-height: 1.35;
}
.check input { width: auto; margin: .15rem 0 0; flex: 0 0 auto; accent-color: var(--focus); }
.check small { display: block; color: var(--muted); font-weight: 400; margin-top: .2rem; }
.check.danger { border-color: #fcd34d; background: var(--warn-bg); }
.check.danger input { accent-color: #b45309; }
.reset-box {
  margin-top: 1.1rem; padding: .95rem 1rem; border-radius: 8px;
  border: 1px solid #fcd34d; background: var(--warn-bg); color: var(--warn);
}
.reset-box strong { display: block; margin: 0 0 .35rem; font-size: .92rem; }
.reset-box > p { margin: 0 0 .75rem; font-size: .88rem; line-height: 1.4; }
.reset-box .check { margin: 0; }
.actions { margin-top: 1.25rem; display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; }
button[type=submit] {
  background: var(--ink); color: #fff; border: 0; border-radius: 8px;
  padding: .8rem 1.25rem; font: inherit; font-weight: 650; cursor: pointer;
}
button[type=submit]:hover { background: #292524; }
button[type=submit]:focus-visible { outline: 3px solid rgba(15,118,110,.35); outline-offset: 2px; }
button.secondary {
  background: #fff; color: var(--ink); border: 1px solid #d6d3d1;
}
button.secondary:hover { background: #f5f5f4; border-color: #a8a29e; }
.error {
  background: var(--danger-bg); color: var(--danger);
  padding: .85rem 1rem; border-radius: 8px; margin: 0 0 1rem; border: 1px solid #fecaca;
}
.notice {
  background: var(--ok-bg); color: var(--ok);
  padding: .85rem 1rem; border-radius: 8px; margin: 0 0 1rem; border: 1px solid #bbf7d0;
}
@media (max-width: 640px) {
  .grid-2 { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <p class="install-brand"><img src="{$e(Nexis::brandUrl($basePath, Nexis::BRAND_LOGO))}" alt="Nexis" width="130" height="48" decoding="async"></p>
    {$langSwitch}
  </div>
  <h1>{$title}</h1>
  <p class="lede">{$lede}</p>
  {$errorHtml}
  {$noticeHtml}
  <div class="card">
    <h2>{$requirementsLabel}</h2>
    <ul class="reqs">{$reqRows}</ul>
  </div>
  <form method="post" action="{$e($basePath)}/install" class="card" autocomplete="on">
    <input type="hidden" name="_csrf" value="{$e($csrf)}">

    <h2>{$sectionWebsite}</h2>
    <div class="grid grid-2">
      <div class="field field-span">
        <label for="site_name">{$fieldSiteName}</label>
        <input id="site_name" name="site_name" type="text" required maxlength="120"
               autocomplete="organization" placeholder="{$fieldSiteNamePh}"
               value="{$val('site_name')}">
      </div>
      <div class="field">
        <label for="primary_domain">{$fieldPrimaryDomain}</label>
        <input id="primary_domain" name="primary_domain" type="text" required maxlength="190"
               autocomplete="off" spellcheck="false" inputmode="url"
               placeholder="localhost" value="{$val('primary_domain')}">
        <p class="hint">{$fieldPrimaryDomainHint}</p>
      </div>
      <div class="field">
        <label for="app_url">{$fieldAppUrl}</label>
        <input id="app_url" name="app_url" type="url" required maxlength="500"
               autocomplete="url" spellcheck="false"
               placeholder="http://localhost/nexis" value="{$val('app_url')}">
        <p class="hint">{$fieldAppUrlHint}</p>
      </div>
      <div class="field field-span">
        <label for="default_locale">{$fieldDefaultLocale}</label>
        <select id="default_locale" name="default_locale">
          <option value="de"{$deSelected}>{$localeDe}</option>
          <option value="en"{$enSelected}>{$localeEn}</option>
        </select>
        <p class="hint">{$fieldDefaultLocaleHint}</p>
      </div>
    </div>

    <h2 style="margin-top:1.5rem">{$sectionAdmin}</h2>
    <div class="grid grid-2">
      <div class="field">
        <label for="admin_name">{$fieldAdminName}</label>
        <input id="admin_name" name="admin_name" type="text" required maxlength="120"
               autocomplete="name" placeholder="Alex Admin"
               value="{$val('admin_name')}">
      </div>
      <div class="field">
        <label for="admin_email">{$fieldAdminEmail}</label>
        <input id="admin_email" name="admin_email" type="email" required maxlength="190"
               autocomplete="username" placeholder="admin@example.com"
               value="{$val('admin_email')}">
      </div>
      <div class="field field-span">
        <label for="admin_password">{$fieldAdminPassword}</label>
        <input id="admin_password" name="admin_password" type="password" required minlength="8"
               autocomplete="new-password" placeholder="{$fieldAdminPasswordPh}"
               value="{$val('admin_password')}">
        <p class="hint">{$fieldAdminPasswordHint}</p>
      </div>
    </div>

    <h2 style="margin-top:1.5rem">{$sectionDatabase}</h2>
    <div class="grid grid-2">
      <div class="field">
        <label for="db_host">{$fieldDbHost}</label>
        <input id="db_host" name="db_host" type="text" required maxlength="190"
               autocomplete="off" spellcheck="false" placeholder="127.0.0.1"
               value="{$val('db_host')}">
      </div>
      <div class="field">
        <label for="db_port">{$fieldDbPort}</label>
        <input id="db_port" name="db_port" type="number" required min="1" max="65535"
               inputmode="numeric" autocomplete="off" placeholder="3306"
               value="{$val('db_port')}">
      </div>
      <div class="field field-span">
        <label for="db_database">{$fieldDbDatabase}</label>
        <input id="db_database" name="db_database" type="text" required maxlength="64"
               pattern="[A-Za-z0-9_]+" autocomplete="off" spellcheck="false"
               placeholder="nexis" value="{$val('db_database')}">
        <p class="hint">{$fieldDbDatabaseHint}</p>
      </div>
      <div class="field">
        <label for="db_username">{$fieldDbUsername}</label>
        <input id="db_username" name="db_username" type="text" required maxlength="190"
               autocomplete="off" spellcheck="false" placeholder="root"
               value="{$val('db_username')}">
      </div>
      <div class="field">
        <label for="db_password">{$fieldDbPassword}</label>
        <input id="db_password" name="db_password" type="password"
               autocomplete="new-password" placeholder="{$fieldDbPasswordPh}"
               value="{$val('db_password')}">
      </div>
    </div>

{$resetBlock}
    <label class="check" style="margin-top:1.1rem">
      <input type="checkbox" name="enable_plugins" value="1"{$pluginsChecked}>
      <span>{$plugins}<small>{$pluginsHint}</small></span>
    </label>

    <div class="actions">
      <button type="submit" name="action" value="install">{$submit}</button>
      <button type="submit" class="secondary" name="action" value="check_db" formnovalidate>{$checkDb}</button>
      <span class="hint">{$submitHint}</span>
    </div>
  </form>
  <p class="lede" style="margin-top:1.5rem;font-size:.85rem"><a href="{$vendorUrl}" target="_blank" rel="noopener">{$attribution}</a></p>
</div>
</body>
</html>
HTML;
    }

    /**
     * @param callable(mixed): string $e
     */
    private function renderLangSwitch(string $basePath, callable $e): string
    {
        $current = $this->ui->locale;
        $aria = $e($this->ui->get('lang_nav'));
        $links = '';
        foreach (['de' => 'Deutsch', 'en' => 'English'] as $code => $label) {
            $href = $e($basePath . '/install?lang=' . $code);
            $currentAttr = $code === $current ? ' aria-current="true"' : '';
            $links .= '<a href="' . $href . '"' . $currentAttr . '>' . $e($label) . '</a>';
        }

        return '<nav class="lang-switch" aria-label="' . $aria . '">' . $links . '</nav>';
    }

    private function renderAlreadyInstalled(string $basePath): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lang = $e($this->ui->locale);
        $title = $e($this->ui->get('already.title'));
        $link = $e($this->ui->get('already.link'));
        $langSwitch = $this->renderLangSwitch($basePath, $e);

        return '<!DOCTYPE html><html lang="' . $lang . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $title . '</title>'
            . '<style>body{font-family:Georgia,serif;padding:2rem;background:#faf8f5;color:#1c1917}'
            . '.lang-switch{display:flex;gap:.35rem;margin:0 0 1rem}.lang-switch a{font:650 .82rem/1.2 system-ui,sans-serif;text-decoration:none;color:#78716c;padding:.35rem .55rem;border-radius:6px;border:1px solid transparent}'
            . '.lang-switch a[aria-current=true]{color:#1c1917;border-color:#e7e5e4;background:#fff}'
            . 'a{color:#0f766e}</style></head><body>'
            . $langSwitch
            . '<h1>' . $title . '</h1>'
            . '<p><a href="' . $e($basePath) . '/admin/login">' . $link . '</a></p>'
            . '</body></html>';
    }

    private function html(string $body, int $status = 200): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'text/html; charset=utf-8'], $body);
    }
}
