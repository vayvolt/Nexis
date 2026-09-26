-- Nexis complete core schema for fresh installs (/install, php bin/migrate.php).
-- Charset: utf8mb4 / InnoDB / UUID as CHAR(36)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE tenants (
    id           CHAR(36)     NOT NULL,
    name         VARCHAR(190) NOT NULL,
    created_at   DATETIME(3)  NOT NULL,
    updated_at   DATETIME(3)  NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id            CHAR(36)     NOT NULL,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name  VARCHAR(190) NOT NULL,
    is_platform_admin TINYINT(1) NOT NULL DEFAULT 0,
    ui_locale     VARCHAR(16)  NOT NULL DEFAULT 'de',
    totp_secret   VARBINARY(255) NULL,
    last_login_at DATETIME(3)  NULL,
    email_verified_at DATETIME(3) NULL,
    created_at    DATETIME(3)  NOT NULL,
    updated_at    DATETIME(3)  NOT NULL,
    deleted_at    DATETIME(3)  NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
    id         CHAR(36)    NOT NULL,
    user_id    CHAR(36)    NOT NULL,
    token_hash CHAR(64)    NOT NULL,
    expires_at DATETIME(3) NOT NULL,
    used_at    DATETIME(3) NULL,
    created_at DATETIME(3) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_reset_token_hash (token_hash),
    KEY idx_password_reset_user (user_id),
    KEY idx_password_reset_expires (expires_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE themes (
    id               CHAR(36)     NOT NULL,
    theme_key        VARCHAR(190) NOT NULL,
    name             VARCHAR(190) NOT NULL,
    version          VARCHAR(50)  NOT NULL,
    compatible_core  VARCHAR(50)  NOT NULL,
    extends_key      VARCHAR(190) NULL,
    manifest         JSON         NOT NULL,
    created_at       DATETIME(3)  NOT NULL,
    updated_at       DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_themes_key (theme_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sites (
    id               CHAR(36)     NOT NULL,
    tenant_id        CHAR(36)     NOT NULL,
    name             VARCHAR(190) NOT NULL,
    primary_domain   VARCHAR(190) NOT NULL,
    default_locale   VARCHAR(16)  NOT NULL,
    locale_url_strategy VARCHAR(16) NOT NULL DEFAULT 'prefix',
    theme_id         CHAR(36)     NULL,
    status           VARCHAR(32)  NOT NULL DEFAULT 'active',
    created_at       DATETIME(3)  NOT NULL,
    updated_at       DATETIME(3)  NOT NULL,
    deleted_at       DATETIME(3)  NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sites_domain (primary_domain),
    KEY idx_sites_tenant (tenant_id),
    CONSTRAINT fk_sites_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sites_theme FOREIGN KEY (theme_id) REFERENCES themes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_domains (
    id         CHAR(36)     NOT NULL,
    site_id    CHAR(36)     NOT NULL,
    host       VARCHAR(190) NOT NULL,
    locale     VARCHAR(16)  NULL,
    is_primary TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_domains_host (host),
    KEY idx_site_domains_site (site_id),
    CONSTRAINT fk_site_domains_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_locales (
    id          CHAR(36)     NOT NULL,
    site_id     CHAR(36)     NOT NULL,
    locale      VARCHAR(16)  NOT NULL,
    label       VARCHAR(80)  NOT NULL,
    url_prefix  VARCHAR(16)  NULL,
    hreflang    VARCHAR(16)  NOT NULL,
    is_default  TINYINT(1)   NOT NULL DEFAULT 0,
    enabled     TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_locales (site_id, locale),
    CONSTRAINT fk_site_locales_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id          CHAR(36)     NOT NULL,
    site_id     CHAR(36)     NULL,
    name        VARCHAR(80)  NOT NULL,
    slug        VARCHAR(80)  NOT NULL,
    is_system   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME(3)  NOT NULL,
    updated_at  DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_scope (site_id, slug),
    CONSTRAINT fk_roles_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id    CHAR(36)     NOT NULL,
    `key` VARCHAR(120) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id       CHAR(36) NOT NULL,
    permission_id CHAR(36) NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_memberships (
    id         CHAR(36)    NOT NULL,
    site_id    CHAR(36)    NOT NULL,
    user_id    CHAR(36)    NOT NULL,
    role_id    CHAR(36)    NOT NULL,
    created_at DATETIME(3) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_membership (site_id, user_id),
    KEY idx_membership_user (user_id),
    CONSTRAINT fk_mem_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
    CONSTRAINT fk_mem_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_mem_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
    id                    CHAR(36)     NOT NULL,
    site_id               CHAR(36)     NOT NULL,
    parent_id             CHAR(36)     NULL,
    translation_group_id  CHAR(36)     NOT NULL,
    type                  VARCHAR(80)  NOT NULL DEFAULT 'page',
    slug                  VARCHAR(190) NOT NULL,
    path                  VARCHAR(500) NOT NULL,
    locale                VARCHAR(16)  NOT NULL,
    title                 VARCHAR(190) NOT NULL,
    body_text             TEXT         NULL,
    status                VARCHAR(32)  NOT NULL DEFAULT 'draft',
    scheduled_at          DATETIME(3)  NULL,
    scheduled_by          CHAR(36)     NULL,
    unpublish_at          DATETIME(3)  NULL,
    unpublish_by          CHAR(36)     NULL,
    published_revision_id CHAR(36)     NULL,
    published_snapshot_id CHAR(36)     NULL,
    meta_title            VARCHAR(190) NULL,
    meta_description      VARCHAR(320) NULL,
    focus_keyword         VARCHAR(120) NULL,
    canonical_url         VARCHAR(500) NULL,
    robots                VARCHAR(80)  NULL DEFAULT 'index,follow',
    search_text           TEXT         NULL,
    created_at            DATETIME(3)  NOT NULL,
    updated_at            DATETIME(3)  NOT NULL,
    deleted_at            DATETIME(3)  NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pages_path (site_id, locale, path),
    UNIQUE KEY uq_pages_group_locale (site_id, translation_group_id, locale),
    KEY idx_pages_parent (parent_id),
    KEY idx_pages_group (translation_group_id),
    KEY idx_pages_status (site_id, status),
    KEY idx_pages_scheduled (status, scheduled_at),
    KEY idx_pages_unpublish (unpublish_at),
    FULLTEXT KEY ft_pages_search (title, meta_title, search_text),
    CONSTRAINT fk_pages_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT,
    CONSTRAINT fk_pages_parent FOREIGN KEY (parent_id) REFERENCES pages (id) ON DELETE SET NULL,
    CONSTRAINT fk_pages_scheduled_by FOREIGN KEY (scheduled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE page_revisions (
    id             CHAR(36)    NOT NULL,
    page_id        CHAR(36)    NOT NULL,
    schema_version INT         NOT NULL DEFAULT 1,
    document       JSON        NOT NULL,
    document_hash  CHAR(64)    NOT NULL,
    message        VARCHAR(190) NULL,
    created_by     CHAR(36)    NULL,
    created_at     DATETIME(3) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_rev_page_created (page_id, created_at),
    CONSTRAINT fk_rev_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE,
    CONSTRAINT fk_rev_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE page_editorial_items (
    id          CHAR(36)     NOT NULL,
    page_id     CHAR(36)     NOT NULL,
    kind        VARCHAR(16)  NOT NULL,
    body        TEXT         NOT NULL,
    status      VARCHAR(16)  NOT NULL DEFAULT 'open',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE page_snapshots (
    id            CHAR(36)    NOT NULL,
    page_id       CHAR(36)    NOT NULL,
    revision_id   CHAR(36)    NOT NULL,
    payload       JSON        NOT NULL,
    payload_hash  CHAR(64)    NOT NULL,
    published_by  CHAR(36)    NULL,
    published_at  DATETIME(3) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_snap_page (page_id, published_at),
    CONSTRAINT fk_snap_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE,
    CONSTRAINT fk_snap_rev FOREIGN KEY (revision_id) REFERENCES page_revisions (id) ON DELETE RESTRICT,
    CONSTRAINT fk_snap_user FOREIGN KEY (published_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pages
    ADD CONSTRAINT fk_pages_pub_rev FOREIGN KEY (published_revision_id) REFERENCES page_revisions (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_pages_pub_snap FOREIGN KEY (published_snapshot_id) REFERENCES page_snapshots (id) ON DELETE SET NULL;

CREATE TABLE menus (
    id         CHAR(36)     NOT NULL,
    site_id    CHAR(36)     NOT NULL,
    handle     VARCHAR(80)  NOT NULL,
    name       VARCHAR(190) NOT NULL,
    locale     VARCHAR(16)  NOT NULL,
    created_at DATETIME(3)  NOT NULL,
    updated_at DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_menus_handle (site_id, handle, locale),
    CONSTRAINT fk_menus_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menu_items (
    id         CHAR(36)     NOT NULL,
    menu_id    CHAR(36)     NOT NULL,
    parent_id  CHAR(36)     NULL,
    page_id    CHAR(36)     NULL,
    label      VARCHAR(190) NOT NULL,
    url        VARCHAR(500) NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    created_at DATETIME(3)  NOT NULL,
    updated_at DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    KEY idx_menu_items_menu (menu_id, sort_order),
    CONSTRAINT fk_mi_menu FOREIGN KEY (menu_id) REFERENCES menus (id) ON DELETE CASCADE,
    CONSTRAINT fk_mi_parent FOREIGN KEY (parent_id) REFERENCES menu_items (id) ON DELETE CASCADE,
    CONSTRAINT fk_mi_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE block_patterns (
    id          CHAR(36)     NOT NULL,
    site_id     CHAR(36)     NOT NULL,
    name        VARCHAR(190) NOT NULL,
    description VARCHAR(500) NULL,
    document    JSON         NOT NULL,
    created_by  CHAR(36)     NULL,
    created_at  DATETIME(3)  NOT NULL,
    updated_at  DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    KEY idx_block_patterns_site (site_id, name),
    CONSTRAINT fk_block_patterns_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
    CONSTRAINT fk_block_patterns_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media_folders (
    id         CHAR(36)     NOT NULL,
    site_id    CHAR(36)     NOT NULL,
    name       VARCHAR(190) NOT NULL,
    sort_order INT          NOT NULL DEFAULT 0,
    created_at DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_media_folder_name (site_id, name),
    KEY idx_media_folders_site (site_id, sort_order),
    CONSTRAINT fk_media_folders_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media_assets (
    id            CHAR(36)     NOT NULL,
    site_id       CHAR(36)     NOT NULL,
    folder_id     CHAR(36)     NULL,
    disk_key      VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    alt_text      VARCHAR(500) NULL,
    mime          VARCHAR(127) NOT NULL,
    extension     VARCHAR(16)  NOT NULL,
    byte_size     INT UNSIGNED NOT NULL,
    width         INT UNSIGNED NULL,
    height        INT UNSIGNED NULL,
    focus_x       DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    focus_y       DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    checksum      CHAR(64)     NOT NULL,
    created_by    CHAR(36)     NULL,
    created_at    DATETIME(3)  NOT NULL,
    deleted_at    DATETIME(3)  NULL,
    PRIMARY KEY (id),
    KEY idx_media_site_created (site_id, created_at),
    KEY idx_media_folder (folder_id),
    UNIQUE KEY uq_media_key (site_id, disk_key),
    CONSTRAINT fk_media_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT,
    CONSTRAINT fk_media_folder FOREIGN KEY (folder_id) REFERENCES media_folders (id) ON DELETE SET NULL,
    CONSTRAINT fk_media_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media_variants (
    id        CHAR(36)     NOT NULL,
    asset_id  CHAR(36)     NOT NULL,
    handle    VARCHAR(80)  NOT NULL,
    disk_key  VARCHAR(500) NOT NULL,
    mime      VARCHAR(127) NOT NULL,
    width     INT UNSIGNED NULL,
    height    INT UNSIGNED NULL,
    byte_size INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_variant (asset_id, handle),
    CONSTRAINT fk_variant_asset FOREIGN KEY (asset_id) REFERENCES media_assets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plugins (
    id              CHAR(36)     NOT NULL,
    plugin_key      VARCHAR(190) NOT NULL,
    name            VARCHAR(190) NOT NULL,
    version         VARCHAR(50)  NOT NULL,
    compatible_core VARCHAR(50)  NOT NULL,
    manifest        JSON         NOT NULL,
    created_at      DATETIME(3)  NOT NULL,
    updated_at      DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_plugins_key (plugin_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE plugin_installations (
    id           CHAR(36)     NOT NULL,
    site_id      CHAR(36)     NOT NULL,
    plugin_id    CHAR(36)     NOT NULL,
    status       VARCHAR(32)  NOT NULL DEFAULT 'installed',
    config       JSON         NULL,
    installed_at DATETIME(3)  NOT NULL,
    updated_at   DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_plugin_install (site_id, plugin_id),
    KEY idx_plugin_status (site_id, status),
    CONSTRAINT fk_pi_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
    CONSTRAINT fk_pi_plugin FOREIGN KEY (plugin_id) REFERENCES plugins (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_theme_overrides (
    site_id    CHAR(36)    NOT NULL,
    tokens     JSON        NOT NULL,
    custom_css MEDIUMTEXT  NULL,
    updated_by CHAR(36)    NULL,
    updated_at DATETIME(3) NOT NULL,
    PRIMARY KEY (site_id),
    CONSTRAINT fk_sto_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE,
    CONSTRAINT fk_sto_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    `key`      VARCHAR(190) NOT NULL,
    value      JSON         NOT NULL,
    updated_at DATETIME(3)  NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_settings (
    id         CHAR(36)     NOT NULL,
    site_id    CHAR(36)     NOT NULL,
    `key`      VARCHAR(190) NOT NULL,
    value      JSON         NOT NULL,
    encrypted  TINYINT(1)   NOT NULL DEFAULT 0,
    updated_at DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_site_settings (site_id, `key`),
    CONSTRAINT fk_ss_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id     CHAR(36)        NULL,
    actor_id    CHAR(36)        NULL,
    action      VARCHAR(120)    NOT NULL,
    entity_type VARCHAR(80)     NULL,
    entity_id   CHAR(36)        NULL,
    ip          VARBINARY(16)   NULL,
    context     JSON            NULL,
    created_at  DATETIME(3)     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_site_time (site_id, created_at),
    KEY idx_audit_actor (actor_id, created_at),
    KEY idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jobs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    queue       VARCHAR(80)     NOT NULL DEFAULT 'default',
    payload     JSON            NOT NULL,
    attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME(3)    NOT NULL,
    created_at  DATETIME(3)     NOT NULL,
    reserved_at DATETIME(3)     NULL,
    PRIMARY KEY (id),
    KEY idx_jobs_poll (queue, available_at, reserved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE failed_jobs (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    queue      VARCHAR(80)     NOT NULL,
    payload    JSON            NOT NULL,
    exception  TEXT            NOT NULL,
    failed_at  DATETIME(3)     NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE idempotency_keys (
    id           CHAR(36)     NOT NULL,
    site_id      CHAR(36)     NOT NULL,
    `key`        VARCHAR(190) NOT NULL,
    request_hash CHAR(64)     NOT NULL,
    response     JSON         NULL,
    status_code  SMALLINT     NOT NULL,
    created_at   DATETIME(3)  NOT NULL,
    expires_at   DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_idem (site_id, `key`),
    KEY idx_idem_exp (expires_at),
    CONSTRAINT fk_idem_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cache_pages (
    id         CHAR(36)     NOT NULL,
    site_id    CHAR(36)     NOT NULL,
    cache_key  VARCHAR(190) NOT NULL,
    body_hash  CHAR(64)     NOT NULL,
    stored_at  DATETIME(3)  NOT NULL,
    expires_at DATETIME(3)  NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cache_pages (site_id, cache_key),
    CONSTRAINT fk_cache_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_endpoints (
    id         CHAR(36)     NOT NULL,
    site_id    CHAR(36)     NOT NULL,
    url        VARCHAR(500) NOT NULL,
    secret     TEXT         NOT NULL,
    events     JSON         NOT NULL,
    is_active  TINYINT(1)   NOT NULL DEFAULT 1,
    created_at DATETIME(3)  NOT NULL,
    updated_at DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    KEY idx_webhook_site (site_id),
    CONSTRAINT fk_webhook_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE webhook_deliveries (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    endpoint_id  CHAR(36)        NOT NULL,
    site_id      CHAR(36)        NOT NULL,
    event_name   VARCHAR(120)    NOT NULL,
    status       VARCHAR(32)     NOT NULL,
    http_status  INT             NULL,
    error        VARCHAR(500)    NULL,
    attempts     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at   DATETIME(3)     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_wh_del_site (site_id, created_at),
    KEY idx_wh_del_endpoint (endpoint_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mail_log (
    id            CHAR(36)     NOT NULL,
    site_id       CHAR(36)     NULL,
    transport     VARCHAR(20)  NOT NULL,
    status        VARCHAR(20)  NOT NULL,
    to_addresses  TEXT         NOT NULL,
    from_address  VARCHAR(190) NULL,
    subject       VARCHAR(500) NOT NULL,
    body_text     MEDIUMTEXT   NOT NULL,
    reply_to      VARCHAR(190) NULL,
    context       VARCHAR(190) NULL,
    error_message TEXT         NULL,
    smtp_log      MEDIUMTEXT   NULL,
    created_at    DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    KEY idx_mail_log_created (created_at),
    KEY idx_mail_log_status (status),
    KEY idx_mail_log_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
