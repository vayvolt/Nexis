CREATE TABLE IF NOT EXISTS plugin_nexis_not_found_hits (
    id CHAR(36) NOT NULL,
    site_id CHAR(36) NOT NULL,
    locale VARCHAR(16) NOT NULL DEFAULT '',
    path VARCHAR(500) NOT NULL,
    hit_count INT UNSIGNED NOT NULL DEFAULT 1,
    last_referer VARCHAR(1000) NULL,
    last_user_agent VARCHAR(500) NULL,
    first_seen_at DATETIME(3) NOT NULL,
    last_seen_at DATETIME(3) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_not_found_site_path_locale (site_id, path, locale),
    KEY idx_not_found_hits (site_id, hit_count DESC, last_seen_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
