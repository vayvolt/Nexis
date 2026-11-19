-- Catalog products live as pages.type = 'product'; meta stub for plugin migrator.
CREATE TABLE IF NOT EXISTS plugin_nexis_catalog_meta (
    id CHAR(36) NOT NULL,
    site_id CHAR(36) NOT NULL,
    created_at DATETIME(3) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_catalog_meta_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
