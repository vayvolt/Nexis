# Nexis

**Nexis** ist ein Produkt von **[Vayvolt](https://www.vayvolt.de)** — ein Homepage-Builder auf **PHP 8.4+** und **MariaDB**.  
Eine Installation verwaltet **eine Website**. Themes, Branding und Plugins erweitern Aussehen und Funktionen.

- Portal & Downloads: [nexis.vayvolt.de](https://nexis.vayvolt.de/)
- Quellcode: [github.com/vayvolt/Nexis](https://github.com/vayvolt/Nexis)

English: [README.en.md](README.en.md)

Aktuell **0.8.0**: Block-Builder, Admin-/Public-i18n (de/en), First-Party-Plugins, optionaler Plugin-/Theme-Marktplatz, **Content-Sync** Staging↔Production (ZIP + CLI), **SSO / OpenID Connect**, **Passkeys**, **Benutzer-Einladung**, **Papierkorb**, leichte **Seitenhierarchie**, Backups inkl. Restore-UI, einheitlicher Cron (CLI + Web), API-CORS, Rollen-Matrix, Launch-Härte (Legal-Seeds, Consent, A11y, Retention, DSAR-Export). Die REST-API (`/api/v1`) liest und schreibt den Kern (Website, Seiten, Übersetzungen, Builder-Dokument mit `If-Match`, Publish/Revert, Medien-Upload, Plugin-Status, Theme-Tokens, Block-Registry) inkl. API-Tokens.

## Voraussetzungen

- PHP 8.4+ mit `intl`, `pdo_mysql`, `gd`, `zip`, `sodium`, `curl`
- Composer
- MariaDB 10.11+ / 11.4 LTS (InnoDB, `utf8mb4`)
- Document Root = Projektordner (kein separates `public/`)

## Installation

### Web-Installer (empfohlen)

1. `composer install`
2. Document Root auf diesen Ordner zeigen (z. B. `http://localhost/nexis/`)
3. `/install` öffnen und Formular ausfüllen

Der Installer legt `.env`, Datenbank und Admin-Konto an und kann First-Party-Plugins aktivieren. Installer und Standardinhalte sind **deutsch und englisch**; die Standard-Sprache ist wählbar.

### CLI (Entwicklung)

1. `composer install`
2. `.env.example` nach `.env` kopieren, DB-Zugangsdaten und einen zufälligen `APP_KEY` setzen
3. Datenbank anlegen: `CREATE DATABASE nexis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
4. `php bin/migrate.php`
5. Optional: `php bin/seed.php` (Demo-Website und Konten; Passwörter in der Ausgabe)
6. Bestehende Installationen ohne AGB/Impressum/Datenschutz: `php bin/seed-legal-pages.php` (oder Admin → Seiten)

## Nach der Installation

| URL | Zweck |
|---|---|
| `/admin/login` | Admin (UI-Sprache umschaltbar) |
| `/` | Öffentliche Site (Prefix der Standard-Locale) |
| `/account` | Kundenkonto (Login, Registrierung, Passwort-Reset) |
| `/admin/plugins` | Plugins, ZIP-Upload |
| `/admin/plugins/marketplace` | Plugin-Marktplatz |
| `/admin/theme` | Theme und Branding |
| `/admin/settings` | Website-, Sprach- und SSO-Einstellungen |
| `/admin/pages` | Seiten inkl. Papierkorb und Hierarchie |
| `/admin/about` | Version und Update-Check |
| `/health` | JSON-Healthcheck |

First-Party-Plugins (Release): Forms, Redirects, Consent, Blog, Katalog.  
Themes (Release): **Nexis** (Install-Default), Atelier, Atelier Warm, Editorial.

### Betrieb

```bash
php bin/cron.php         # Queue, Publish, Retention, Backup-Planer (eine Cron-Zeile)
# Web-Hoster ohne CLI: cron.php?key=… + CRON_SECRET in .env (docs/ops/cron.md)
php bin/export-site.php  # Content-ZIP Staging→Prod (docs/ops/content-sync.md)
php bin/import-site.php archive.zip --dry-run|--yes
composer backup          # DB-Dump + Medien → storage/backups/
composer restore         # Backup auflisten / zurückspielen (--latest --yes)
```

Go-Live: [`docs/ops/go-live.md`](docs/ops/go-live.md) | Queue: [`docs/ops/queue.md`](docs/ops/queue.md)

## Für Entwickler

```bash
composer test
composer analyse
```

Technischer Rahmen: PSR-4/7/11/14/15, PHP-Views (Admin), Twig (öffentliche Themes), JSON-Blockschema (Builder).

```text
nexis/                 # Document Root
├── index.php          # Front Controller
├── src/               # Kern
├── plugins/           # Plugins
├── themes/            # Themes
├── resources/         # Lang, Admin-Views
├── database/          # core_schema.sql
├── docs/              # Architektur und Verträge
└── tests/
```

Dokumentation: [`docs/README.md`](docs/README.md) — Architektur, Datenmodell, Plugins, Theming, Mehrsprachigkeit, Sicherheit/Betrieb, API, Marktplatz, ADRs.  
Änderungen: [`CHANGELOG.md`](CHANGELOG.md) | English: [`CHANGELOG.en.md`](CHANGELOG.en.md).

## Lizenz

**[GNU GPL v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html)** (`GPL-2.0-or-later`).  
Volltext: [`LICENSE`](LICENSE) | Drittanbieter: [`THIRD_PARTY_NOTICES.md`](THIRD_PARTY_NOTICES.md).

Copyright (C) 2026 Vayvolt | [vayvolt.de](https://www.vayvolt.de)
