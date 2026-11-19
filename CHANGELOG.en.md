# Changelog

Notable changes to Nexis. Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows SemVer.

German: [CHANGELOG.md](CHANGELOG.md)

## [Unreleased]

## [0.8.0] – 2026-11-19

### Added

- **Content sync staging ↔ production**: hardened export/import package (`nexis.site.export` v3) under `/admin/export` — pages including drafts, media updates, menus, redirects, theme/settings; dry-run in the UI
- CLI `php bin/export-site.php` / `php bin/import-site.php` (`composer export-site` / `import-site`); ops docs [docs/ops/content-sync.md](docs/ops/content-sync.md)
- Web cron `cron.php` for hosts without CLI: same run as `bin/cron.php`, gated by `CRON_SECRET` (query `key` or header `X-Nexis-Cron-Key`)
- Passkeys on the customer account under `/account` (Security): register and remove
- **Security hub** at `/admin/security`: status cards for SSO, registration, tokens, webhooks, backup, debug, plus recent login failures
- Builder: unsaved-changes indicator, `beforeunload`, save with Ctrl/Cmd+S
- Pages list: search (title/path), status and display-locale filters
- **Admin global search** (Ctrl/Cmd+K or `/`): pages, media, users via header palette and `GET /api/v1/admin/search`
- Menu editor: dynamic add/remove rows, parent by label select, ↑/↓ on handle; hardened parent mapping on save
- Admin: one-line CMS update bar above the navbar when the catalog reports a newer release

### Changed

- First-party plugins/themes: `compatibleCore` set to `^0.8`
- Content export stores media under `media/{disk_key}`; import reads v3 and falls back to v2 basename
- Personal 2FA/passkeys only under `/account` (no longer under `/admin/security`)
- Admin UI: full-width cards on desktop, fields in a dynamic `form-grid` (`auto-fill`); shared utilities `.form-hint`, `.form-section`, `.table-wrap` (Settings, Theme, API tokens, Webhooks, Backups, Media, Mail)
- Admin lists: `.table-wrap` / empty states; plugin uninstall and role reset use the shared confirm dialog
- Media: copy feedback i18n including `aria-live`
- Backup/restore and CMS upgrade safety dump: pure PHP/PDO (`PhpSqlDump`) — no `mysqldump`/`mysql` client, no `exec`/`proc_open`

### Fixed

- Theme marketplace: update banner no longer sticks after install (cache clear + filter against local version)

## [0.7.0] – 2026-11-18

### Added

- **SSO / OpenID Connect** for admin login: one IdP (Google, Microsoft, Keycloak, …) under Site settings → SSO; button on `/admin/login`; link existing users by verified email (`oauth_identities`); optional auto-provisioning with selectable system role
- Redirect URI hint in settings; client secret encrypted in `site_settings`
- **User invite** by email (`/admin/users` → Invite): link `/account/invite`, recipient sets password; open invites revocable with confirmation dialog; HTML invite mail
- **Passkeys / WebAuthn** under `/admin/security`; as second login factor beside or instead of TOTP on `/admin/login/2fa`
- Account dashboard: **To Admin** button when CMS access exists
- Redirects plugin: configurable minimum hits for 404 log display (`min_hits`)
- CMS upgrade: background worker (`bin/cms-upgrade.php`) with live step UI under **About Nexis**; request fallback if the worker does not start

### Changed

- First-party plugins/themes: `compatibleCore` set to `^0.7`
- External login (OIDC) skips local TOTP – MFA stays with the IdP; audit/event channel `oidc`
- Password login requires a second factor when TOTP **or** a passkey is registered
- Admin timestamps (mail log, logs, audit, redirects) unified via `AdminDateTime` (Europe/Berlin, locale-aware, no ms)
- API token scopes and webhook events as tables with short descriptions; webhook with no event preselected
- Locales settings: tighter edit layout with aligned actions

### Fixed

- PHPUnit: doc-comment metadata in `ShopHookHelpersTest` converted to attributes

## [0.6.0] – 2026-11-16

### Added

- **Trash** for pages (`/admin/pages/trash`): soft-delete remembers path/slug (`restore_path` / `restore_slug`), restore as draft, permanent delete including revisions/snapshots
- **Page hierarchy** (light): choose parent page in the builder; path is built under the parent; page list indented by path depth

### Changed

- Soft-delete dialog and messages talk about trash instead of “irreversible”
- First-party plugins/themes: `compatibleCore` set to `^0.6`
- Core migrator runs on admin / admin-API requests (pending SQL + ensure*), not on the public site; CLI/install/upgrade unchanged

### Fixed

- Trash: `restore_path`/`restore_slug` were missing while `migrate()` only ran on install/upgrade – soft-delete failed with “Unknown column restore_path”
- Trash: permanent delete failed silently because soft-delete already nulls `published_*` and MariaDB `UPDATE` `rowCount` returns 0

## [0.5.0] – 2026-11-12

### Added

- Backup **restore in the admin UI** (`/admin/backups`): confirmation dialog, optional media replace, audit `backup.restore`; CLI remains (`php bin/restore.php`)
- Backups stored **directly as ZIP** under `storage/backups/YYYYMMDD-HHMMSS.zip` (download streams the file; legacy folders stay readable)
- Unified cron entry `php bin/cron.php` / `composer cron` (queue, publish, retention, backup planner); template `docs/ops/crontab.example` (replaces `queue-work.php`)
- API **CORS** via `API_CORS_ORIGINS` (comma-separated origins or `*`); preflight `OPTIONS` on `/api/v1/*`
- Admin loading states (`data-busy`) for backup create/restore, publish, and CMS upgrade; empty state for empty backup list
- Section block: optional `anchor` prop sets an HTML `id` (one-pager jump targets); builder labels DE/EN

### Changed

- First-party plugins/themes: `compatibleCore` set to `^0.5`

### Fixed

- Backup: `mysqldump` without `--routines` (exit 2 after MariaDB upgrade with inconsistent `mysql.proc`); stderr shown in the error message
- Table block: caption outside the horizontal scroll wrapper (correct left alignment)
- Core `content-blocks.css`: table head with readable text color; caption/scroll structure aligned with new markup

## [0.4.1] – 2026-11-06

### Added

- Terms/AGB seed (DE/EN) on install/seed including footer links; registration requires accepting privacy + terms with audit `auth.registration.legal_accepted`
- Account data export under `/account/export` (JSON, no password/2FA secret) including audit `account.data_exported`
- Skip link “To content” in theme templates; operator DPA/subprocessor checklist under `docs/ops/dpa-subprocessors.md`
- Location block: OSM map only after click (“Load map”), no automatic third-party request; A11y target WCAG 2.2 AA in `docs/ops/accessibility.md`
- Existing installs: pull legal pages via admin (Pages → notice) or `php bin/seed-legal-pages.php` / `composer seed-legal` (imprint, privacy, terms; idempotent, does not overwrite existing pages)
- Theme admin colours: contrast hint and live contrast check (WCAG 2.2 AA, text/primary/accent on surface)
- Retention via env: `AUDIT_RETENTION_DAYS` (default 180) and `FORMS_RETENTION_DAYS` (default 365) for purge in `bin/queue-work.php`; operator docs `docs/ops/retention.md`
- Third-party/SDK inventory for operators under `docs/ops/vendor-inventory.md` (consent, OSM, Google Fonts, update-check)
- Operator guide notice/takedown (DMCA/DSA) under `docs/ops/notice-takedown.md`
- Data-minimisation hint in the forms builder and on registration (name/email/password only)

### Fixed

- Public pages: Nexis favicon missing in theme `<head>` (admin only had it); now via `theme.head` including fallback views
- Page builder: inline script for SEO/title sync violated CSP `script-src 'self'`; logic moved to `assets/admin/builder.js`
- Header language switcher: with multiple locales a compact dropdown instead of all codes side by side (themes + `locale-menu.js`); Escape closes the menu
- Consent (`nexis/consent`): analytics no longer preselected; Google tags (gtag/GTM) load only after opt-in (Consent Mode defaults stay in head)
- Consent: keyboard – focus on action, Escape, focus returns to opener; `:focus-visible` on banner controls
- First-party themes: consistent `:focus-visible` outlines for links, buttons, nav, and forms
- Admin UI language switch keeps tab hash (`#colors`, etc.)

## [0.4.0] – 2026-10-03

### Added

- Read REST API (`/api/v1`) as MVP: public (`sites/current`, `pages`, `pages/by-path`, `pages/{id}`) and admin (`site`, `pages`, `pages/{id}`, `pages/{id}/document`, `media`, `tokens`) with a uniform error envelope including `requestId`
- Write admin API: `PATCH /api/v1/admin/site` (`settings.manage`), `POST /api/v1/admin/pages` (`201` + `Location`), `PATCH /api/v1/admin/pages/{id}` (SEO fields also with `content.page.seo`), `PUT /api/v1/admin/pages/{id}/document` plus `POST /api/v1/admin/pages/{id}/publish` and `.../unpublish` (`content.page.publish`); `POST /api/v1/admin/media` (`multipart/form-data`, `content.media.manage`, `201` + `Location`)
- `POST /api/v1/admin/pages/{id}/translations` creates the target-locale version in the same translation group (`copyBlocks` optional, default `true`) and is idempotent: if the locale already exists, returns `200` with that page instead of a second page; `GET /api/v1/admin/pages/{id}/alternates` lists locales in the group
- `POST /api/v1/admin/pages/{id}/revert` (`content.page.publish`) writes an old revision as a new draft; unknown revision → `404`, concurrent write → `409` `page.conflict`
- `GET /api/v1/admin/plugins` plus `POST /api/v1/admin/plugins/{vendor}/{name}/enable` and `.../disable` (`plugin.manage`) with audit entry and `plugin.enabled`/`plugin.disabled` webhooks like the admin UI
- `GET` and `PATCH /api/v1/admin/theme/tokens` (`theme.manage`, `customCss` additionally `theme.custom_css`): partial merge of branding overrides, `null` removes a token, values equal to the theme default are not stored, unknown token keys yield `422`
- `GET /api/v1/admin/blocks` returns the block registry including plugin blocks (`type`, `label`, `allowsChildren`, `propsSchema`, `defaultProps`) for clients that build documents themselves
- Optimistic concurrency for the builder document: `If-Match` with `documentHash` is required (`428` `page.if_match_required`), stale hash yields `409` `page.conflict` including the current hash
- JSON bodies on write endpoints accept only documented fields: invalid JSON → `400` `http.invalid_json`, unknown keys or wrong types → `422` with `details`
- API tokens (`api_tokens`, `Authorization: Bearer nx_…`): manage under `/admin/api-tokens` (`settings.manage`), optional scopes per permission, revoke, `last_used_at`; only the SHA-256 hash is stored and the secret is shown once
- Backup management under `/admin/backups` (`settings.manage`): list with time, size, and contents, “Back up now” (media optional), download as ZIP or `database.sql` only, delete with confirmation
- Backup planner (`backup.schedule.enabled` / `.interval` / `.retain` / `.include_media`): creates due backups and removes everything beyond retention; runs at the end of `php bin/queue-work.php` or via cron with `php bin/backup.php --scheduled` (file lock against parallel runs)
- `bin/backup.php` supports `--no-media` for database-only backups
- `bin/restore.php` / `composer restore` to restore `storage/backups/{timestamp}` (DB + optional media); incomplete backups are discarded on failure
- Shared `LogicalBackup` service; web installer creates a backup before “empty database” and restores it if installation fails
- In-app CMS upgrade under `/admin/about` (`settings.manage`): backup → catalog ZIP → core files → migration; on failure DB restore
- Code snapshot before file apply including rollback of code + DB; maintenance mode off again after successful rollback (updates stay manual)
- Manual reset to older catalog versions (`GET /api/v1/cms/releases` + About UI); no reverse migrations on downgrade
- Theme catalog like plugins: directory `/themes` + `GET /api/v1/themes*`, CMS `/admin/theme/marketplace` (browse/install via `ThemePackageInstaller`)
- Theme updates in directory `update-check`; portal seed (`ThemeSeeder`, `pack-seed-themes.php`) for first-party themes
- Theme submission/review in the directory (`kind=theme`, `theme.json`, no PHP; approval → `themes`/`theme_releases`)
- Directory star ratings for plugins/themes (`package_ratings`: one vote per developer, admin delete; list/detail + API fields `ratingAverage`/`ratingCount`)
- Form builder in `nexis/forms` 1.1: form definitions (`plugin_nexis_forms_definitions`), field types including select/checkbox/number, simple visibility conditions (`visibleWhen`), admin under `/admin/forms/builder`, dynamic block/submit including `payload_json`, client JS for conditional fields
- Roles matrix under `/admin/roles` (`users.manage`): role × permission, site admin fixed, reset to system templates; custom grants survive seeder/plugin sync

### Fixed

- PHP 8.5: `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY` → `Pdo\Mysql::ATTR_USE_BUFFERED_QUERY`; removed deprecated `curl_close()` calls
- Plugin catalog: updates/install only when `compatibleCore` matches the running Nexis version; warning and no update button when incompatible

### Changed

- Primary navigation: child items as hover/focus dropdown (desktop); mobile still indented below
- CSRF check skipped for requests with `Authorization: Bearer` (no cookie, no session); `/api/v1/admin` responds unauthenticated with JSON `401` instead of HTML redirect and stays reachable in maintenance mode
- `bin/backup.php` aborts on dump/media errors and deletes the incomplete backup directory; note for CMS update workflow
- First-party plugins and themes: `compatibleCore` set to `^0.4`

## [0.3.0] – 2026-09-26

### Added

- Block patterns and media folders (admin + builder)
- Core blocks: gallery (`core/gallery`), video/embed YouTube/Vimeo (`core/embed`), table CSV (`core/table`)
- Unpublish in the builder (withdraw snapshot) including webhook `page.unpublished`
- Media focus point (`focus_x`/`focus_y`, object-position) and scheduled unpublish (`unpublish_at`)
- Global content: header CTA + footer teaser (`/admin/globals`, theme slots)
- Image variant UI (list original/thumb/WebP, regenerate)
- Review workflow: status `in_review`, permission `content.page.submit_review` (editors without publish), approve/reject in the builder, webhooks `page.review_submitted` / `page.review_rejected`
- Builder as the central workspace: page/SEO, content, actions, scheduling, and advanced in one flow; separate page editing removed
- Editorial comments and tasks in the builder (`page_editorial_items`)
- SEO in the builder: focus keyword, checklist/score, search and social preview (`focus_keyword`)
- Redirects: CSV import and export (`nexis/redirects`); 404 log stores again (PDO named parameters)
- Structured data in core: JSON-LD (`WebPage`/`FAQPage`/`WebSite`/`Organization`) + event `StructuredDataBuilding` for plugins; FAQ from `nexis/faq/*`; product/offer and block `nexis/product/details` in plugin `nexis/catalog`
- Role templates out of the box: **Editor** (`editor`) and **SEO** (`seo`) beside site admin/member; new permission `content.page.seo` (SEO role without block editing); redirects plugin also grants `redirects.manage` to SEO
- Public error pages (404/500) and admin 500 as HTML instead of JSON only; theme templates `error` / `maintenance`
- Maintenance mode under Settings → Operations (`site.maintenance.*`, 503 page, CMS bypass)
- Log viewer under System → Logs (`/admin/logs`, reads `storage/logs/app.log`)
- Health dashboard under System → Health (`/admin/health`: queue depth, disk, PHP, DB)

### Changed

- Boot: plugin/theme catalog sync and asset publish skip unchanged entries; theme resolve without catalog resync per request
- Page cache: expiry check in UTC (previously an immediate miss under Europe/Berlin)
- Admin pages open directly in the builder (title, meta, robots on save)
- Deleting a page removes the whole translation group (all languages) including modal confirmation; soft-delete frees the URL path (recreate without FK errors)
- System role “Editorial” → “Editor”; editor defaults without theme/audit/publish permissions
- Own admin account: admin rights not self-editable; at least one site/platform admin remains

## [0.2.1] – 2026-09-20

First public distribution release (ZIP via directory download).

### Included

- Self-hosted homepage builder (one site per install)
- Web installer, block builder, media, menus, users/roles, 2FA, queue, mail
- Admin UI and public surface (de/en)
- Themes: **Nexis** (install default), Atelier, Editorial
- First-party plugins: Blog, Catalog, Consent, Forms, Redirects
- Schema via `database/core_schema.sql`
- Optional plugin marketplace (directory: `https://nexis.vayvolt.de`)
- Update-check with anonymous install ID (one install = one stats record)

### Notes

- Document root = project root; `.env` from `.env.example` or web installer
- Queue worker in production: `php bin/queue-work.php`
