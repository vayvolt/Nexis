CREATE TABLE IF NOT EXISTS plugin_nexis_consent_meta (
    site_id    CHAR(36)     NOT NULL,
    updated_at DATETIME(3)  NOT NULL,
    PRIMARY KEY (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
