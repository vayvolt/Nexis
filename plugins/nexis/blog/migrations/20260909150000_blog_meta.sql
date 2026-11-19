-- Blog posts live as pages.type = 'post'; meta stub for plugin migrator.
CREATE TABLE IF NOT EXISTS plugin_nexis_blog_meta (
    id CHAR(36) NOT NULL,
    site_id CHAR(36) NOT NULL,
    created_at DATETIME(3) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_blog_meta_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
