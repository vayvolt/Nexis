ALTER TABLE plugin_nexis_forms_submissions
    ADD COLUMN read_at DATETIME(3) NULL AFTER created_at,
    ADD KEY idx_forms_site_unread (site_id, read_at);
