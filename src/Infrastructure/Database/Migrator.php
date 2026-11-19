<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Database;

use Nexis\Auth\Permission;
use Nexis\Auth\PdoPermissionLookup;
use Nexis\Auth\SystemRoleSeeder;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Fresh installs: applies database/core_schema.sql once.
 * Existing installs: applies pending files under database/migrations/.
 */
final class Migrator
{
    public function __construct(
        private PDO $pdo,
        private string $schemaPath,
        private ?string $migrationsPath = null,
    ) {
        if ($this->migrationsPath === null) {
            $this->migrationsPath = dirname($this->schemaPath) . DIRECTORY_SEPARATOR . 'migrations';
        }
    }

    /**
     * @return list<string>
     */
    public function migrate(): array
    {
        $this->ensureRegistry();
        $applied = [];

        if ($this->isFreshInstall()) {
            if (!is_file($this->schemaPath)) {
                throw new RuntimeException('core_schema.sql fehlt: ' . $this->schemaPath);
            }
            $this->runSqlFile($this->schemaPath);
            $this->markApplied('core_schema.sql');
            $applied[] = 'core_schema.sql';
            // Schema already includes later increments — mark them applied without re-running.
            foreach ($this->listIncrementalFiles() as $file) {
                $name = basename($file);
                if (!$this->isApplied($name)) {
                    $this->markApplied($name);
                    $applied[] = $name;
                }
            }
            $this->ensureMediaFolderColumn();
            $this->ensureMediaFocusColumns();
            $this->ensurePageUnpublishColumns();
            $this->ensureFocusKeywordColumn();
            $this->ensurePageEditorialItemsTable();
            $this->ensurePageTrashRestoreColumns();
            $this->ensureSoftDeletedPagePaths();
            $this->ensureWorkflowPermissions();
            $this->ensureSystemRoleTemplates();
            $this->ensureApiTokensTable();
            $this->ensureOauthIdentitiesTable();
            $this->ensureUserInvitesTable();
            $this->ensureWebauthnCredentialsTable();

            return $applied;
        }

        foreach ($this->listIncrementalFiles() as $file) {
            $name = basename($file);
            if ($this->isApplied($name)) {
                continue;
            }
            $this->runSqlFile($file);
            $this->markApplied($name);
            $applied[] = $name;
        }

        $this->ensureMediaFolderColumn();
        $this->ensureMediaFocusColumns();
        $this->ensurePageUnpublishColumns();
        $this->ensureFocusKeywordColumn();
        $this->ensurePageEditorialItemsTable();
        $this->ensurePageTrashRestoreColumns();
        $this->ensureSoftDeletedPagePaths();
        $this->ensureWorkflowPermissions();
        $this->ensureSystemRoleTemplates();
        $this->ensureApiTokensTable();
        $this->ensureOauthIdentitiesTable();
        $this->ensureUserInvitesTable();
        $this->ensureWebauthnCredentialsTable();

        return $applied;
    }

    /**
     * Admin invite-by-link tokens (email + role → accept sets password).
     */
    private function ensureUserInvitesTable(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $exists = $this->pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'user_invites' LIMIT 1",
            );
            if ($exists !== false && $exists->fetchColumn() !== false) {
                return;
            }
            $this->pdo->exec(
                'CREATE TABLE user_invites (
                    id TEXT NOT NULL PRIMARY KEY,
                    site_id TEXT NOT NULL,
                    email TEXT NOT NULL,
                    display_name TEXT NOT NULL,
                    role_id TEXT NOT NULL,
                    is_platform_admin INTEGER NOT NULL DEFAULT 0,
                    token_hash TEXT NOT NULL,
                    invited_by TEXT NOT NULL,
                    expires_at TEXT NOT NULL,
                    accepted_at TEXT NULL,
                    created_at TEXT NOT NULL
                )',
            );
            $this->pdo->exec('CREATE UNIQUE INDEX uq_user_invites_token ON user_invites (token_hash)');
            $this->pdo->exec('CREATE INDEX idx_user_invites_site ON user_invites (site_id)');

            return;
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'user_invites'");
        if ($table !== false && $table->fetch() !== false) {
            return;
        }
        $sites = $this->pdo->query("SHOW TABLES LIKE 'sites'");
        if ($sites === false || $sites->fetch() === false) {
            return;
        }
        $this->pdo->exec(
            'CREATE TABLE user_invites (
                id                CHAR(36)     NOT NULL,
                site_id           CHAR(36)     NOT NULL,
                email             VARCHAR(190) NOT NULL,
                display_name      VARCHAR(190) NOT NULL,
                role_id           CHAR(36)     NOT NULL,
                is_platform_admin TINYINT(1)   NOT NULL DEFAULT 0,
                token_hash        CHAR(64)     NOT NULL,
                invited_by        CHAR(36)     NOT NULL,
                expires_at        DATETIME(3)  NOT NULL,
                accepted_at       DATETIME(3)  NULL,
                created_at        DATETIME(3)  NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_user_invites_token (token_hash),
                KEY idx_user_invites_site (site_id),
                CONSTRAINT fk_user_invites_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    /**
     * Passkey / WebAuthn credentials per user.
     */
    private function ensureWebauthnCredentialsTable(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $exists = $this->pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'webauthn_credentials' LIMIT 1",
            );
            if ($exists !== false && $exists->fetchColumn() !== false) {
                return;
            }
            $this->pdo->exec(
                'CREATE TABLE webauthn_credentials (
                    id TEXT NOT NULL PRIMARY KEY,
                    user_id TEXT NOT NULL,
                    credential_id TEXT NOT NULL,
                    public_key TEXT NOT NULL,
                    sign_count INTEGER NOT NULL DEFAULT 0,
                    transports TEXT NULL,
                    name TEXT NOT NULL,
                    created_at TEXT NOT NULL,
                    last_used_at TEXT NULL
                )',
            );
            $this->pdo->exec('CREATE UNIQUE INDEX uq_webauthn_credential_id ON webauthn_credentials (credential_id)');
            $this->pdo->exec('CREATE INDEX idx_webauthn_user ON webauthn_credentials (user_id)');

            return;
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'webauthn_credentials'");
        if ($table !== false && $table->fetch() !== false) {
            return;
        }
        $users = $this->pdo->query("SHOW TABLES LIKE 'users'");
        if ($users === false || $users->fetch() === false) {
            return;
        }
        $this->pdo->exec(
            'CREATE TABLE webauthn_credentials (
                id            CHAR(36)     NOT NULL,
                user_id       CHAR(36)     NOT NULL,
                credential_id VARCHAR(1024) NOT NULL,
                public_key    TEXT         NOT NULL,
                sign_count    INT UNSIGNED NOT NULL DEFAULT 0,
                transports    VARCHAR(255) NULL,
                name          VARCHAR(120) NOT NULL,
                created_at    DATETIME(3)  NOT NULL,
                last_used_at  DATETIME(3)  NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_webauthn_credential_id (credential_id),
                KEY idx_webauthn_user (user_id),
                CONSTRAINT fk_webauthn_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    /**
     * Linked OIDC subjects for SSO login (`provider` + `subject` → user).
     */
    private function ensureOauthIdentitiesTable(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $exists = $this->pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'oauth_identities' LIMIT 1",
            );
            if ($exists !== false && $exists->fetchColumn() !== false) {
                return;
            }
            $this->pdo->exec(
                'CREATE TABLE oauth_identities (
                    id TEXT NOT NULL PRIMARY KEY,
                    user_id TEXT NOT NULL,
                    provider TEXT NOT NULL,
                    subject TEXT NOT NULL,
                    email_at_link TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                )',
            );
            $this->pdo->exec('CREATE UNIQUE INDEX uq_oauth_identities_provider_subject ON oauth_identities (provider, subject)');
            $this->pdo->exec('CREATE INDEX idx_oauth_identities_user ON oauth_identities (user_id)');

            return;
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'oauth_identities'");
        if ($table !== false && $table->fetch() !== false) {
            return;
        }
        $users = $this->pdo->query("SHOW TABLES LIKE 'users'");
        if ($users === false || $users->fetch() === false) {
            return;
        }
        $this->pdo->exec(
            'CREATE TABLE oauth_identities (
                id            CHAR(36)     NOT NULL,
                user_id       CHAR(36)     NOT NULL,
                provider      VARCHAR(64)  NOT NULL,
                subject       VARCHAR(255) NOT NULL,
                email_at_link VARCHAR(190) NULL,
                created_at    DATETIME(3)  NOT NULL,
                updated_at    DATETIME(3)  NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_oauth_identities_provider_subject (provider, subject),
                KEY idx_oauth_identities_user (user_id),
                CONSTRAINT fk_oauth_identities_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    /**
     * API tokens for the Admin-API (`Authorization: Bearer nx_…`).
     */
    private function ensureApiTokensTable(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $exists = $this->pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'api_tokens' LIMIT 1",
            );
            if ($exists !== false && $exists->fetchColumn() !== false) {
                return;
            }
            $this->pdo->exec(
                'CREATE TABLE api_tokens (
                    id TEXT NOT NULL PRIMARY KEY,
                    site_id TEXT NOT NULL,
                    user_id TEXT NOT NULL,
                    name TEXT NOT NULL,
                    token_prefix TEXT NOT NULL,
                    token_hash TEXT NOT NULL,
                    scopes_json TEXT NOT NULL,
                    last_used_at TEXT NULL,
                    expires_at TEXT NULL,
                    revoked_at TEXT NULL,
                    created_at TEXT NOT NULL
                )',
            );
            $this->pdo->exec('CREATE UNIQUE INDEX uq_api_tokens_hash ON api_tokens (token_hash)');
            $this->pdo->exec('CREATE INDEX idx_api_tokens_site ON api_tokens (site_id)');
            $this->pdo->exec('CREATE INDEX idx_api_tokens_user ON api_tokens (user_id)');

            return;
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'api_tokens'");
        if ($table !== false && $table->fetch() !== false) {
            return;
        }
        $sites = $this->pdo->query("SHOW TABLES LIKE 'sites'");
        if ($sites === false || $sites->fetch() === false) {
            return;
        }
        $this->pdo->exec(
            'CREATE TABLE api_tokens (
                id           CHAR(36)     NOT NULL,
                site_id      CHAR(36)     NOT NULL,
                user_id      CHAR(36)     NOT NULL,
                name         VARCHAR(120) NOT NULL,
                token_prefix VARCHAR(16)  NOT NULL,
                token_hash   CHAR(64)     NOT NULL,
                scopes_json  TEXT         NOT NULL,
                last_used_at DATETIME(3)  NULL,
                expires_at   DATETIME(3)  NULL,
                revoked_at   DATETIME(3)  NULL,
                created_at   DATETIME(3)  NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_api_tokens_hash (token_hash),
                KEY idx_api_tokens_site (site_id),
                KEY idx_api_tokens_user (user_id),
                CONSTRAINT fk_api_tokens_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
                CONSTRAINT fk_api_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    private function ensureMediaFolderColumn(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $exists = $this->pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'media_assets' LIMIT 1",
            );
            if ($exists === false || $exists->fetchColumn() === false) {
                return;
            }
            $cols = $this->pdo->query('PRAGMA table_info(media_assets)');
            if ($cols === false) {
                return;
            }
            foreach ($cols->fetchAll(PDO::FETCH_ASSOC) as $col) {
                if (($col['name'] ?? '') === 'folder_id') {
                    return;
                }
            }
            $this->pdo->exec('ALTER TABLE media_assets ADD COLUMN folder_id TEXT NULL');

            return;
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'media_assets'");
        if ($table === false || $table->fetch() === false) {
            return;
        }

        $stmt = $this->pdo->query("SHOW COLUMNS FROM media_assets LIKE 'folder_id'");
        if ($stmt !== false && $stmt->fetch() !== false) {
            return;
        }
        $this->pdo->exec(
            'ALTER TABLE media_assets
                ADD COLUMN folder_id CHAR(36) NULL AFTER site_id,
                ADD KEY idx_media_folder (folder_id),
                ADD CONSTRAINT fk_media_folder FOREIGN KEY (folder_id) REFERENCES media_folders (id) ON DELETE SET NULL',
        );
    }

    private function ensureMediaFocusColumns(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $exists = $this->pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'media_assets' LIMIT 1",
            );
            if ($exists === false || $exists->fetchColumn() === false) {
                return;
            }
            $cols = $this->pdo->query('PRAGMA table_info(media_assets)');
            if ($cols === false) {
                return;
            }
            $names = [];
            foreach ($cols->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $names[(string) ($col['name'] ?? '')] = true;
            }
            if (!isset($names['focus_x'])) {
                $this->pdo->exec('ALTER TABLE media_assets ADD COLUMN focus_x REAL NOT NULL DEFAULT 50');
            }
            if (!isset($names['focus_y'])) {
                $this->pdo->exec('ALTER TABLE media_assets ADD COLUMN focus_y REAL NOT NULL DEFAULT 50');
            }

            return;
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'media_assets'");
        if ($table === false || $table->fetch() === false) {
            return;
        }
        $stmt = $this->pdo->query("SHOW COLUMNS FROM media_assets LIKE 'focus_x'");
        if ($stmt !== false && $stmt->fetch() !== false) {
            return;
        }
        $this->pdo->exec(
            'ALTER TABLE media_assets
                ADD COLUMN focus_x DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER height,
                ADD COLUMN focus_y DECIMAL(5,2) NOT NULL DEFAULT 50.00 AFTER focus_x',
        );
    }

    private function ensurePageUnpublishColumns(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $exists = $this->pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'pages' LIMIT 1",
            );
            if ($exists === false || $exists->fetchColumn() === false) {
                return;
            }
            $cols = $this->pdo->query('PRAGMA table_info(pages)');
            if ($cols === false) {
                return;
            }
            $names = [];
            foreach ($cols->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $names[(string) ($col['name'] ?? '')] = true;
            }
            if (!isset($names['unpublish_at'])) {
                $this->pdo->exec('ALTER TABLE pages ADD COLUMN unpublish_at TEXT NULL');
            }
            if (!isset($names['unpublish_by'])) {
                $this->pdo->exec('ALTER TABLE pages ADD COLUMN unpublish_by TEXT NULL');
            }

            return;
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'pages'");
        if ($table === false || $table->fetch() === false) {
            return;
        }
        $stmt = $this->pdo->query("SHOW COLUMNS FROM pages LIKE 'unpublish_at'");
        if ($stmt !== false && $stmt->fetch() !== false) {
            return;
        }
        $this->pdo->exec(
            'ALTER TABLE pages
                ADD COLUMN unpublish_at DATETIME(3) NULL AFTER scheduled_by,
                ADD COLUMN unpublish_by CHAR(36) NULL AFTER unpublish_at,
                ADD KEY idx_pages_unpublish (unpublish_at)',
        );
    }

    private function ensureFocusKeywordColumn(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $exists = $this->pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'pages' LIMIT 1",
            );
            if ($exists === false || $exists->fetchColumn() === false) {
                return;
            }
            $cols = $this->pdo->query('PRAGMA table_info(pages)');
            if ($cols === false) {
                return;
            }
            foreach ($cols->fetchAll(PDO::FETCH_ASSOC) as $col) {
                if (($col['name'] ?? '') === 'focus_keyword') {
                    return;
                }
            }
            $this->pdo->exec('ALTER TABLE pages ADD COLUMN focus_keyword TEXT NULL');

            return;
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'pages'");
        if ($table === false || $table->fetch() === false) {
            return;
        }
        $stmt = $this->pdo->query("SHOW COLUMNS FROM pages LIKE 'focus_keyword'");
        if ($stmt !== false && $stmt->fetch() !== false) {
            return;
        }
        $this->pdo->exec(
            'ALTER TABLE pages
                ADD COLUMN focus_keyword VARCHAR(120) NULL AFTER meta_description',
        );
    }

    private function ensurePageEditorialItemsTable(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $exists = $this->pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'page_editorial_items' LIMIT 1",
            );
            if ($exists !== false && $exists->fetchColumn() !== false) {
                return;
            }
            $this->pdo->exec(
                'CREATE TABLE page_editorial_items (
                    id TEXT NOT NULL PRIMARY KEY,
                    page_id TEXT NOT NULL,
                    kind TEXT NOT NULL,
                    body TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT \'open\',
                    created_by TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    resolved_at TEXT NULL,
                    resolved_by TEXT NULL
                )',
            );

            return;
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'page_editorial_items'");
        if ($table !== false && $table->fetch() !== false) {
            return;
        }
        $pages = $this->pdo->query("SHOW TABLES LIKE 'pages'");
        if ($pages === false || $pages->fetch() === false) {
            return;
        }
        $this->pdo->exec(
            'CREATE TABLE page_editorial_items (
                id          CHAR(36)     NOT NULL,
                page_id     CHAR(36)     NOT NULL,
                kind        VARCHAR(16)  NOT NULL,
                body        TEXT         NOT NULL,
                status      VARCHAR(16)  NOT NULL DEFAULT \'open\',
                created_by  CHAR(36)     NULL,
                created_at  DATETIME(3)  NOT NULL,
                updated_at  DATETIME(3)  NOT NULL,
                resolved_at DATETIME(3)  NULL,
                resolved_by CHAR(36)     NULL,
                PRIMARY KEY (id),
                KEY idx_page_editorial_page (page_id, created_at),
                CONSTRAINT fk_page_editorial_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE,
                CONSTRAINT fk_page_editorial_author FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
                CONSTRAINT fk_page_editorial_resolver FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    private function ensurePageTrashRestoreColumns(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $exists = $this->pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'pages' LIMIT 1",
            );
            if ($exists === false || $exists->fetchColumn() === false) {
                return;
            }
            $cols = $this->pdo->query('PRAGMA table_info(pages)');
            if ($cols === false) {
                return;
            }
            $names = [];
            foreach ($cols->fetchAll(PDO::FETCH_ASSOC) as $col) {
                $names[(string) ($col['name'] ?? '')] = true;
            }
            if (!isset($names['restore_path'])) {
                $this->pdo->exec('ALTER TABLE pages ADD COLUMN restore_path TEXT NULL');
            }
            if (!isset($names['restore_slug'])) {
                $this->pdo->exec('ALTER TABLE pages ADD COLUMN restore_slug TEXT NULL');
            }

            return;
        }

        $table = $this->pdo->query("SHOW TABLES LIKE 'pages'");
        if ($table === false || $table->fetch() === false) {
            return;
        }
        $stmt = $this->pdo->query("SHOW COLUMNS FROM pages LIKE 'restore_path'");
        if ($stmt !== false && $stmt->fetch() !== false) {
            return;
        }
        $this->pdo->exec(
            'ALTER TABLE pages
                ADD COLUMN restore_path VARCHAR(500) NULL AFTER search_text,
                ADD COLUMN restore_slug VARCHAR(190) NULL AFTER restore_path',
        );
    }

    /**
     * Soft-deleted pages must not keep live paths (unique key + recreate safety).
     */
    private function ensureSoftDeletedPagePaths(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $exists = $this->pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'pages' LIMIT 1",
            );
            if ($exists === false || $exists->fetchColumn() === false) {
                return;
            }
        } else {
            $table = $this->pdo->query("SHOW TABLES LIKE 'pages'");
            if ($table === false || $table->fetch() === false) {
                return;
            }
        }

        $stmt = $this->pdo->query(
            "SELECT id, path, slug FROM pages
             WHERE deleted_at IS NOT NULL
               AND path NOT LIKE '/.deleted/%'",
        );
        if ($stmt === false) {
            return;
        }
        $update = $this->pdo->prepare(
            'UPDATE pages
             SET restore_path = COALESCE(restore_path, :remember_path),
                 restore_slug = COALESCE(restore_slug, :remember_slug),
                 path = :path,
                 slug = :slug
             WHERE id = :id',
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $update->execute([
                'remember_path' => (string) ($row['path'] ?? ''),
                'remember_slug' => (string) ($row['slug'] ?? ''),
                'path' => '/.deleted/' . $id,
                'slug' => 'deleted-' . substr(str_replace('-', '', $id), 0, 12),
                'id' => $id,
            ]);
        }
    }

    /**
     * Register review permission and align system editor role
     * (submit for review; publish stays with website-admin).
     */
    private function ensureWorkflowPermissions(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $exists = $this->pdo->query(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'permissions' LIMIT 1",
            );
            if ($exists === false || $exists->fetchColumn() === false) {
                return;
            }
        } else {
            $table = $this->pdo->query("SHOW TABLES LIKE 'permissions'");
            if ($table === false || $table->fetch() === false) {
                return;
            }
        }

        $permissions = new PdoPermissionLookup($this->pdo);
        $permissions->ensurePermissions(Permission::core());

        $roles = $this->pdo->query(
            "SELECT id FROM roles WHERE slug = 'editor' AND is_system = 1",
        );
        if ($roles === false) {
            return;
        }
        foreach ($roles->fetchAll(PDO::FETCH_COLUMN) as $roleId) {
            if (!is_string($roleId) || $roleId === '') {
                continue;
            }
            $permissions->grantRole($roleId, [Permission::CONTENT_PAGE_SUBMIT_REVIEW, Permission::CONTENT_PAGE_SEO]);
            $permissions->revokeRole($roleId, [Permission::CONTENT_PAGE_PUBLISH]);
        }

        $admins = $this->pdo->query(
            "SELECT id FROM roles WHERE slug = 'admin' AND is_system = 1",
        );
        if ($admins === false) {
            return;
        }
        foreach ($admins->fetchAll(PDO::FETCH_COLUMN) as $roleId) {
            if (!is_string($roleId) || $roleId === '') {
                continue;
            }
            $permissions->grantRole($roleId, Permission::core());
        }
    }

    /**
     * Out-of-the-box role templates: Website-Admin, Redakteur, SEO, Mitglied.
     */
    private function ensureSystemRoleTemplates(): void
    {
        $seeder = new SystemRoleSeeder($this->pdo, new PdoPermissionLookup($this->pdo));
        $seeder->ensureForAllSites();
    }

    /**
     * @return list<string>
     */
    private function listIncrementalFiles(): array
    {
        $dir = $this->migrationsPath;
        if ($dir === null || $dir === '' || !is_dir($dir)) {
            return [];
        }
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql');
        if ($files === false) {
            return [];
        }
        sort($files, SORT_STRING);

        return array_values(array_filter($files, static fn (string $f): bool => is_file($f)));
    }

    private function isApplied(string $version): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :version LIMIT 1');
        $stmt->execute(['version' => $version]);

        return $stmt->fetchColumn() !== false;
    }

    private function isFreshInstall(): bool
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM schema_migrations');
        if ($stmt === false) {
            throw new RuntimeException('schema_migrations konnte nicht gelesen werden.');
        }

        return (int) $stmt->fetchColumn() === 0;
    }

    private function runSqlFile(string $path): void
    {
            $sql = file_get_contents($path);
            if ($sql === false) {
            throw new RuntimeException('SQL-Datei konnte nicht gelesen werden: ' . $path);
            }

            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $useTransaction = $driver === 'sqlite';
            if ($useTransaction) {
                $this->pdo->beginTransaction();
            }

            try {
                foreach (self::splitStatements($sql) as $statement) {
                $this->runStatement($statement);
            }
                if ($useTransaction) {
                    $this->pdo->commit();
                }
            } catch (\Throwable $exception) {
                if ($useTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $exception;
            }
    }

    /**
     * Drain SELECT/SHOW result sets so MySQL unbuffered connections stay usable.
     * Additive ALTER TABLE that hits an existing column/index is treated as success
     * (ensure* helpers may have applied the same change before the SQL migration ran).
     */
    private function runStatement(string $statement): void
    {
        $trimmed = ltrim($statement);
        if (preg_match('/^(SELECT|SHOW|WITH|EXPLAIN|DESCRIBE|DESC)\b/i', $trimmed) === 1) {
            $result = $this->pdo->query($statement);
            if ($result !== false) {
                $result->fetchAll();
                $result->closeCursor();
            }

            return;
        }

        try {
            $this->pdo->exec($statement);
        } catch (PDOException $e) {
            if ($this->isIgnorableDuplicateDdl($e, $trimmed)) {
                return;
            }
            throw $e;
        }
    }

    private function isIgnorableDuplicateDdl(PDOException $e, string $statement): bool
    {
        if (preg_match('/^\s*ALTER\s+TABLE\b/i', $statement) !== 1) {
            return false;
        }
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        // 1060 duplicate column, 1061 duplicate key name
        return $sqlState === '42S21' || $driverCode === 1060 || $driverCode === 1061;
    }

    private function markApplied(string $version): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)',
        );
        $insert->execute([
            'version' => $version,
            'applied_at' => gmdate('Y-m-d H:i:s.v'),
        ]);
    }

    private function ensureRegistry(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS schema_migrations (
                    version TEXT NOT NULL PRIMARY KEY,
                    applied_at TEXT NOT NULL
                )',
            );

            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(190) NOT NULL,
                applied_at DATETIME(3) NOT NULL,
                PRIMARY KEY (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    /**
     * @return list<string>
     */
    public static function splitStatements(string $sql): array
    {
        $withoutComments = preg_replace('/^\s*--.*$/m', '', $sql);
        if (!is_string($withoutComments)) {
            return [];
        }

        $parts = preg_split('/;\s*$/m', $withoutComments) ?: [];
        $statements = [];
        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
        }

        return $statements;
    }
}
