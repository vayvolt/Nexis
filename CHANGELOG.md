# Changelog

Alle wesentlichen Änderungen an Nexis. Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), Versionierung nach SemVer.

## [Unreleased]

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
