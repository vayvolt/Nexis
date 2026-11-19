CREATE TABLE plugin_nexis_redirects (
    id          CHAR(36)     NOT NULL,
    site_id     CHAR(36)     NOT NULL,
    locale      VARCHAR(16)  NULL,
    from_path   VARCHAR(500) NOT NULL,
    to_url      VARCHAR(500) NOT NULL,
    status_code SMALLINT     NOT NULL DEFAULT 301,
    created_at  DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_redirect_from (site_id, locale, from_path),
    KEY idx_redirect_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
