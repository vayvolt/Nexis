# Changelog

Alle wesentlichen Änderungen an Nexis. Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), Versionierung nach SemVer.

English: [CHANGELOG.en.md](CHANGELOG.en.md)

## [Unreleased]

## [0.8.0] – 2026-11-19

### Added

- **Content-Sync Staging ↔ Production**: gehärtetes Export/Import-Paket (`nexis.site.export` v3) unter `/admin/export` — Seiten inkl. Entwürfe, Medien-Update, Menüs, Redirects, Theme/Settings; Dry-Run in der UI
- CLI `php bin/export-site.php` / `php bin/import-site.php` (`composer export-site` / `import-site`); Ops-Doku [docs/ops/content-sync.md](docs/ops/content-sync.md)
- Web-Cron `cron.php` für Hoster ohne CLI: gleicher Lauf wie `bin/cron.php`, Absicherung über `CRON_SECRET` (Query `key` oder Header `X-Nexis-Cron-Key`)
- Passkeys im Kundenkonto unter `/account` (Sicherheit): registrieren und löschen
- **Sicherheits-Hub** unter `/admin/security`: Status-Karten zu SSO, Registrierung, Tokens, Webhooks, Backup, Debug sowie letzte Login-Fehler
- Builder: Hinweis auf ungespeicherte Änderungen, `beforeunload`, Speichern per Ctrl/Cmd+S
- Seitenliste: Suche (Titel/Pfad), Status- und Anzeige-Sprachfilter
- **Admin-Globalsuche** (Ctrl/Cmd+K oder `/`): Seiten, Medien, Benutzer über Header-Palette und `GET /api/v1/admin/search`
- Menü-Editor: Zeilen dynamisch add/remove, Eltern per Label-Select, ↑/↓ am Griff; Parent-Mapping beim Speichern gehärtet
- Admin: einzeilige CMS-Update-Leiste über der Navbar, wenn der Katalog ein neueres Release meldet

### Changed

- First-Party Plugins/Themes: `compatibleCore` auf `^0.8`
- Content-Export speichert Medien unter `media/{disk_key}`; Import liest v3 und fällt auf v2-Basename zurück
- Persönliche 2FA/Passkeys nur noch unter `/account` (nicht mehr unter `/admin/security`)
- Admin-UI: Desktop volle Kartenbreite, Felder in dynamischem `form-grid` (`auto-fill`); gemeinsame Utilities `.form-hint`, `.form-section`, `.table-wrap` (Settings, Theme, API-Tokens, Webhooks, Backups, Media, Mail)
- Admin-Listen: `.table-wrap` / Empty-States; Plugin-Deinstall und Rollen-Reset über gemeinsamen Confirm-Dialog
- Media: Copy-Feedback i18n inkl. `aria-live`
- Backup/Restore und CMS-Upgrade-Sicherung: rein über PHP/PDO (`PhpSqlDump`) — kein `mysqldump`/`mysql`-Client, kein `exec`/`proc_open`

### Fixed

- Theme-Marketplace: Update-Banner bleibt nach Install nicht hängen (Cache-Clear + Filter gegen lokale Version)

## [0.7.0] – 2026-11-18

### Added

- **SSO / OpenID Connect** für den Admin-Login: ein IdP (Google, Microsoft, Keycloak, …) unter Website-Einstellungen → SSO; Button auf `/admin/login`; Verknüpfung bestehender Benutzer per verifizierter E-Mail (`oauth_identities`); optional Auto-Provisioning mit wählbarer Systemrolle
- Redirect-URI-Hinweis in den Einstellungen; Client-Secret verschlüsselt in `site_settings`
- **Benutzer-Einladung** per E-Mail (`/admin/users` → Einladen): Link `/account/invite`, Empfänger setzt Passwort; offene Einladungen widerrufbar mit Bestätigungsdialog; HTML-Einladungsmail
- **Passkeys / WebAuthn** unter `/admin/security`; als zweite Login-Stufe neben oder statt TOTP auf `/admin/login/2fa`
- Konto-Dashboard: Button **Zum Admin**, wenn CMS-Zugriff besteht
- Redirects-Plugin: konfigurierbare Mindesttreffer für die 404-Protokoll-Anzeige (`min_hits`)
- CMS-Upgrade: Hintergrund-Worker (`bin/cms-upgrade.php`) mit Live-Schrittanzeige unter **Über Nexis**; Request-Fallback falls der Worker nicht startet

### Changed

- First-Party Plugins/Themes: `compatibleCore` auf `^0.7`
- Externer Login (OIDC) überspringt lokales TOTP – MFA liegt beim IdP; Audit/Event-Kanal `oidc`
- Passwort-Login verlangt zweite Stufe, wenn TOTP **oder** Passkey registriert ist
- Admin-Zeitstempel (Mail-Log, Logs, Audit, Redirects) einheitlich über `AdminDateTime` (Europe/Berlin, sprachabhängig, ohne ms)
- API-Token-Scopes und Webhook-Events tabellarisch mit Kurzbeschreibung; Webhook ohne Event-Vorauswahl
- Locales-Einstellungen: kompakteres Bearbeitungs-Layout mit ausgerichteten Aktionen

### Fixed

- PHPUnit: Doc-Comment-Metadata in `ShopHookHelpersTest` auf Attributes umgestellt

## [0.6.0] – 2026-11-16

### Added

- **Papierkorb** für Seiten (`/admin/pages/trash`): Soft-Delete merkt sich Pfad/Slug (`restore_path` / `restore_slug`), Wiederherstellen als Entwurf, endgültig löschen inkl. Revisionen/Snapshots
- **Seitenhierarchie** (leicht): Elternseite im Builder wählen; Pfad wird unter der Elternseite gebildet; Seitenliste mit Einrückung nach Pfadtiefe

### Changed

- Soft-Delete-Dialog und -Meldungen sprechen vom Papierkorb statt „unwiderruflich“
- First-Party Plugins/Themes: `compatibleCore` auf `^0.6`
- Core-Migrator läuft bei Admin-/Admin-API-Requests (pending SQL + ensure*), nicht auf der öffentlichen Site; CLI/Install/Upgrade bleiben

### Fixed

- Papierkorb: `restore_path`/`restore_slug` fehlten, solange `migrate()` nur bei Install/Upgrade lief – Soft-Delete brach mit „Unknown column restore_path“ ab
- Papierkorb: endgültiges Löschen scheiterte still, weil Soft-Delete `published_*` schon nullt und MariaDB `UPDATE`-`rowCount` 0 liefert

## [0.5.0] – 2026-11-12

### Added

- Backup-**Restore in der Admin-UI** (`/admin/backups`): Bestätigungsdialog, optional Medien ersetzen, Audit `backup.restore`; CLI bleibt (`php bin/restore.php`)
- Backups werden **direkt als ZIP** unter `storage/backups/YYYYMMDD-HHMMSS.zip` abgelegt (Download streamt die Datei; Legacy-Ordner bleiben lesbar)
- Einheitlicher Cron-Einstieg `php bin/cron.php` / `composer cron` (Queue, Publish, Retention, Backup-Planer); Vorlage `docs/ops/crontab.example` (ersetzt `queue-work.php`)
- API-**CORS** über `API_CORS_ORIGINS` (kommaseparierte Origins oder `*`); Preflight `OPTIONS` auf `/api/v1/*`
- Admin Loading-States (`data-busy`) für Backup erstellen/Restore, Publish und CMS-Upgrade; Empty-State für leere Backup-Liste
- Abschnitts-Block: optionales Prop `anchor` setzt eine HTML-`id` (für One-Pager-Sprungmarken); Builder-Label DE/EN

### Changed

- First-Party Plugins/Themes: `compatibleCore` auf `^0.5`

### Fixed

- Backup: `mysqldump` ohne `--routines` (Exit 2 nach MariaDB-Upgrade bei inkonsistentem `mysql.proc`); stderr erscheint in der Fehlermeldung
- Tabellen-Block: Caption außerhalb des horizontalen Scroll-Wrappers (korrekt linksbündig)
- Core `content-blocks.css`: Tabellenkopf mit lesbarer Textfarbe; Caption-/Scroll-Struktur an neuen Markup-Stand angepasst

## [0.4.1] – 2026-11-06

### Added

- AGB/Terms-Seed (DE/EN) bei Installation/Seed inkl. Footer-Links; Registrierung verlangt Annahme von Datenschutz + AGB mit Audit-Eintrag `auth.registration.legal_accepted`
- Konto-Datenexport unter `/account/export` (JSON, ohne Passwort/2FA-Geheimnis) inkl. Audit `account.data_exported`
- Skip-Link „Zum Inhalt“ in Theme-Templates; Betreiber-Checkliste AVV/Unterauftragsverarbeiter unter `docs/ops/dpa-subprocessors.md`
- Standort-Block: OSM-Karte erst nach Klick („Karte laden“), kein automatischer Drittanbieter-Request; A11y-Ziel WCAG 2.2 AA in `docs/ops/accessibility.md`
- Bestehende Installationen: Legal-Seiten nachziehen via Admin (Seiten → Hinweis) oder `php bin/seed-legal-pages.php` / `composer seed-legal` (Impressum, Datenschutz, AGB; idempotent, überschreibt keine vorhandenen Seiten)
- Theme-Admin Farben: Kontrast-Hinweis und Live-Kontrastprüfung (WCAG 2.2 AA, Text/Primär/Akzent auf Fläche)
- Retention per Env: `AUDIT_RETENTION_DAYS` (Default 180) und `FORMS_RETENTION_DAYS` (Default 365) für den Purge in `bin/queue-work.php`; Betreiber-Doku `docs/ops/retention.md`
- Drittanbieter-/SDK-Inventar für Betreiber unter `docs/ops/vendor-inventory.md` (Consent, OSM, Google Fonts, Update-Check)
- Betreiber-Leitfaden Notice/Takedown (DMCA/DSA) unter `docs/ops/notice-takedown.md`
- Datensparsamkeit-Hinweis im Forms-Builder und bei der Registrierung (nur Name/E-Mail/Passwort)

### Fixed

- Öffentliche Seiten: Nexis-Favicon fehlte im Theme-`<head>` (nur Admin hatte es); jetzt über `theme.head` inkl. Fallback-Views
- Page-Builder: Inline-Script für SEO-/Titel-Sync verstieß gegen CSP `script-src 'self'`; Logik nach `assets/admin/builder.js` verschoben
- Sprachumschalter im Header: bei mehreren Locales kompakter Dropdown statt aller Codes nebeneinander (Themes + `locale-menu.js`); Escape schließt Menü
- Consent (`nexis/consent`): Analytics nicht mehr vorausgewählt; Google-Tags (gtag/GTM) erst nach Opt-in laden (Consent-Mode-Defaults bleiben im Head)
- Consent: Tastatur – Fokus auf Aktion, Escape, Fokus zurück zum Öffner; `:focus-visible` auf Banner-Controls
- First-Party-Themes: einheitliche `:focus-visible`-Umrisse für Links, Buttons, Nav und Formulare
- Admin UI-Sprachwechsel behält Tab-Hash (`#colors` usw.)

## [0.4.0] – 2026-10-03

### Added

- Lesende REST-API (`/api/v1`) als MVP: Public (`sites/current`, `pages`, `pages/by-path`, `pages/{id}`) und Admin (`site`, `pages`, `pages/{id}`, `pages/{id}/document`, `media`, `tokens`) mit einheitlichem Fehler-Envelope inkl. `requestId`
- Schreibende Admin-API: `PATCH /api/v1/admin/site` (`settings.manage`), `POST /api/v1/admin/pages` (`201` + `Location`), `PATCH /api/v1/admin/pages/{id}` (SEO-Felder auch mit `content.page.seo`), `PUT /api/v1/admin/pages/{id}/document` sowie `POST /api/v1/admin/pages/{id}/publish` und `.../unpublish` (`content.page.publish`); `POST /api/v1/admin/media` (`multipart/form-data`, `content.media.manage`, `201` + `Location`)
- `POST /api/v1/admin/pages/{id}/translations` legt die Fassung einer Ziel-Locale in derselben Übersetzungsgruppe an (`copyBlocks` optional, Standard `true`) und ist idempotent: existiert die Locale schon, kommt `200` mit dieser Seite statt einer zweiten Seite; `GET /api/v1/admin/pages/{id}/alternates` listet die Locales der Gruppe
- `POST /api/v1/admin/pages/{id}/revert` (`content.page.publish`) schreibt eine alte Revision als neuen Entwurf; unbekannte Revision → `404`, paralleler Schreibzugriff → `409` `page.conflict`
- `GET /api/v1/admin/plugins` sowie `POST /api/v1/admin/plugins/{vendor}/{name}/enable` und `.../disable` (`plugin.manage`) mit Audit-Eintrag und `plugin.enabled`/`plugin.disabled`-Webhooks wie in der Admin-UI
- `GET` und `PATCH /api/v1/admin/theme/tokens` (`theme.manage`, `customCss` zusätzlich `theme.custom_css`): partielles Merge der Branding-Overrides, `null` entfernt einen Token, Werte gleich dem Theme-Default werden nicht gespeichert, unbekannte Token-Keys ergeben `422`
- `GET /api/v1/admin/blocks` liefert die Block-Registry inkl. Plugin-Blöcken (`type`, `label`, `allowsChildren`, `propsSchema`, `defaultProps`) für Clients, die Dokumente selbst bauen
- Optimistic Concurrency für das Builder-Dokument: `If-Match` mit dem `documentHash` ist Pflicht (`428` `page.if_match_required`), veralteter Hash ergibt `409` `page.conflict` inkl. aktuellem Hash
- JSON-Bodys der Schreib-Endpunkte akzeptieren nur dokumentierte Felder: ungültiges JSON → `400` `http.invalid_json`, unbekannte Schlüssel oder falsche Typen → `422` mit `details`
- API-Tokens (`api_tokens`, `Authorization: Bearer nx_…`): Verwaltung unter `/admin/api-tokens` (Recht `settings.manage`), optionale Scopes je Recht, Widerruf, `last_used_at`; gespeichert wird nur der SHA-256-Hash und das Geheimnis wird einmalig angezeigt
- Backup-Verwaltung unter `/admin/backups` (Recht `settings.manage`): Liste mit Zeit, Größe und Bestandteilen, „Jetzt sichern“ (Medien abwählbar), Download als ZIP oder nur `database.sql`, Löschen mit Bestätigung
- Backup-Planer (`backup.schedule.enabled` / `.interval` / `.retain` / `.include_media`): erstellt fällige Backups und entfernt alles jenseits der Aufbewahrung; ausgeführt am Ende von `php bin/queue-work.php` oder per Cron mit `php bin/backup.php --scheduled` (Datei-Lock gegen Parallelläufe)
- `bin/backup.php` kennt `--no-media` für reine Datenbank-Backups
- `bin/restore.php` / `composer restore` zum Zurückspielen von `storage/backups/{timestamp}` (DB + optional Medien); unvollständige Backups werden bei Fehlern verworfen
- Gemeinsamer `LogicalBackup`-Service; Web-Installer legt vor „Datenbank leeren“ ein Backup an und spielt es bei Installationsfehler zurück
- In-App-CMS-Upgrade unter `/admin/about` (Recht `settings.manage`): Backup → Katalog-ZIP → Core-Dateien → Migration; bei Fehler DB-Restore
- Code-Snapshot vor Datei-Apply inkl. Rollback von Code + DB; Wartungsmodus nach erfolgreichem Rollback wieder aus (Updates bleiben manuell)
- Manuelles Zurücksetzen auf ältere Katalog-Versionen (`GET /api/v1/cms/releases` + About-UI); bei Downgrade keine Rückwärts-Migrationen
- Theme-Katalog analog Plugins: Directory `/themes` + `GET /api/v1/themes*`, CMS `/admin/theme/marketplace` (Browse/Install via `ThemePackageInstaller`)
- Theme-Updates im Directory-`update-check`; Portal-Seed (`ThemeSeeder`, `pack-seed-themes.php`) für First-Party-Themes
- Theme-Einreichung/Review im Directory (`kind=theme`, `theme.json`, kein PHP; Freigabe → `themes`/`theme_releases`)
- Directory-Sterne-Bewertungen für Plugins/Themes (`package_ratings`: eine Stimme pro Developer, Admin-Löschen; List/Detail + API-Felder `ratingAverage`/`ratingCount`)
- Formular-Builder in `nexis/forms` 1.1: Form-Definitionen (`plugin_nexis_forms_definitions`), Feldtypen inkl. Select/Checkbox/Number, einfache Sichtbarkeitsbedingungen (`visibleWhen`), Admin unter `/admin/forms/builder`, dynamischer Block/Submit inkl. `payload_json`, Client-JS für bedingte Felder
- Rollen-Matrix unter `/admin/roles` (Recht `users.manage`): Rolle × Permission, Website-Admin fest, Reset auf System-Templates; Custom-Grants bleiben nach Seeder-/Plugin-Sync erhalten

### Fixed

- PHP 8.5: `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY` → `Pdo\Mysql::ATTR_USE_BUFFERED_QUERY`; veraltete `curl_close()`-Aufrufe entfernt
- Plugin-Katalog: Updates/Installation nur bei passendem `compatibleCore` zur laufenden Nexis-Version; Warnung und kein Update-Button bei Inkompabilität

### Changed

- Hauptnavigation: Kind-Menüpunkte als Hover-/Focus-Dropdown (Desktop); mobil weiterhin eingerückt darunter
- CSRF-Prüfung entfällt für Requests mit `Authorization: Bearer` (kein Cookie, keine Session); `/api/v1/admin` antwortet unauthentifiziert mit JSON `401` statt HTML-Redirect und bleibt im Wartungsmodus erreichbar
- `bin/backup.php` bricht bei Dump-/Medien-Fehlern ab und löscht das unvollständige Backup-Verzeichnis; Hinweis für CMS-Update-Workflow
- First-Party-Plugins und -Themes: `compatibleCore` auf `^0.4`

## [0.3.0] – 2026-09-26

### Added

- Block-Patterns und Medien-Ordner (Admin + Builder)
- Core-Blöcke: Galerie (`core/gallery`), Video/Embed YouTube/Vimeo (`core/embed`), Tabelle CSV (`core/table`)
- Unpublish im Builder (Snapshot zurücknehmen) inkl. Webhook `page.unpublished`
- Medien-Fokuspunkt (`focus_x`/`focus_y`, object-position) und geplante Unpublish (`unpublish_at`)
- Globale Inhalte: Header-CTA + Footer-Teaser (`/admin/globals`, Theme-Slots)
- Bildvarianten-UI (Original/Thumb/WebP auflisten, neu erzeugen)
- Review-Workflow: Status `in_review`, Recht `content.page.submit_review` (Redakteure ohne Publish), Freigeben/Ablehnen im Builder, Webhooks `page.review_submitted` / `page.review_rejected`
- Builder als zentrale Arbeitsfläche: Seite/SEO, Inhalt, Aktionen, Zeitplanung und Erweitert in einem Flow; getrennte Seitenbearbeitung entfällt
- Redaktionskommentare und Aufgaben im Builder (`page_editorial_items`)
- SEO im Builder: Focus-Keyword, Checkliste/Score, Such- und Social-Vorschau (`focus_keyword`)
- Redirects: CSV-Import und -Export (`nexis/redirects`); 404-Protokoll speichert wieder (PDO-Named-Parameter)
- Structured Data im Core: JSON-LD (`WebPage`/`FAQPage`/`WebSite`/`Organization`) + Event `StructuredDataBuilding` für Plugins; FAQ aus `nexis/faq/*`; Produkt/Offer und Block `nexis/product/details` im Plugin `nexis/catalog`
- Rollen-Templates out of the box: **Redakteur** (`editor`) und **SEO** (`seo`) neben Website-Admin/Mitglied; neues Recht `content.page.seo` (SEO-Rolle ohne Blockbearbeitung); Redirects-Plugin vergibt `redirects.manage` auch an SEO
- Öffentliche Fehlerseiten (404/500) und Admin-500 als HTML statt nur JSON; Theme-Templates `error` / `maintenance`
- Wartungsmodus unter Einstellungen → Betrieb (`site.maintenance.*`, 503-Seite, CMS-Bypass)
- Log-Viewer unter System → Logs (`/admin/logs`, liest `storage/logs/app.log`)
- Health-Dashboard unter System → Health (`/admin/health`: Queue-Tiefe, Disk, PHP, DB)

### Changed

- Boot: Plugin-/Theme-Katalog-Sync und Asset-Publish überspringen unveränderte Einträge; Theme-Resolve ohne Katalog-Resync pro Request
- Page-Cache: Ablaufprüfung in UTC (vorher sofortiger Miss bei Europe/Berlin)
- Admin-Seiten öffnen direkt im Builder (Titel, Meta, Robots beim Speichern)
- Seiten löschen entfernt die gesamte Übersetzungsgruppe (alle Sprachen) inkl. Modal-Bestätigung; Soft-Delete gibt den URL-Pfad frei (Recreate ohne FK-Fehler)
- System-Rolle „Redaktion“ → „Redakteur“; Editor-Defaults ohne Theme-/Audit-/Publish-Rechte
- Eigenes Admin-Konto: Admin-Rechte nicht selbst änderbar; mindestens ein Website-/Plattform-Admin bleibt

## [0.2.1] – 2026-09-20

Erstes öffentliches Distributions-Release (ZIP über Directory-Download).

### Enthalten

- Self-hosted Homepage-Builder (eine Website pro Installation)
- Web-Installer, Block-Builder, Medien, Menüs, Benutzer/Rollen, 2FA, Queue, Mail
- Admin-UI und öffentliche Oberfläche (de/en)
- Themes: **Nexis** (Install-Default), Atelier, Editorial
- First-Party-Plugins: Blog, Katalog, Consent, Forms, Redirects
- Schema über `database/core_schema.sql`
- Optionaler Plugin-Marktplatz (Directory: `https://nexis.vayvolt.de`)
- Update-Check mit anonymer Install-ID (eine Installation = ein Statistik-Datensatz)

### Hinweise

- Document Root = Projektroot; `.env` aus `.env.example` bzw. Web-Installer
- Queue-Worker im Betrieb: `php bin/queue-work.php`
