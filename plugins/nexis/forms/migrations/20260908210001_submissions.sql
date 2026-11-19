CREATE TABLE plugin_nexis_forms_submissions (
    id         CHAR(36)     NOT NULL,
    site_id    CHAR(36)     NOT NULL,
    locale     VARCHAR(16)  NOT NULL,
    name       VARCHAR(190) NOT NULL,
    email      VARCHAR(190) NOT NULL,
    message    TEXT         NOT NULL,
    created_at DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    KEY idx_forms_site_created (site_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
