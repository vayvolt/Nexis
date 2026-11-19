CREATE TABLE IF NOT EXISTS plugin_nexis_forms_definitions (
    id              CHAR(36)     NOT NULL,
    site_id         CHAR(36)     NOT NULL,
    slug            VARCHAR(80)  NOT NULL,
    name            VARCHAR(190) NOT NULL,
    fields_json     MEDIUMTEXT   NOT NULL,
    success_message VARCHAR(500) NULL,
    submit_label    VARCHAR(120) NULL,
    created_at      DATETIME(3)  NOT NULL,
    updated_at      DATETIME(3)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_forms_definitions_site_slug (site_id, slug),
    KEY idx_forms_definitions_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE plugin_nexis_forms_submissions
    ADD COLUMN form_id CHAR(36) NULL AFTER site_id,
    ADD COLUMN payload_json MEDIUMTEXT NULL AFTER message;
