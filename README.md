# Nexis

**Nexis** ist ein Produkt von **[Vayvolt](https://www.vayvolt.de)** — ein Homepage-Builder auf **PHP 8.4+** und **MariaDB**.  
Eine Installation verwaltet **eine Website**. Themes, Branding und Plugins erweitern Aussehen und Funktionen.

- Portal & Downloads: [nexis.vayvolt.de](https://nexis.vayvolt.de/)
- Quellcode: [github.com/vayvolt/Nexis](https://github.com/vayvolt/Nexis)

Aktuell **0.3.0**: Block-Builder, Admin-/Public-i18n (de/en), First-Party-Plugins, optionaler Plugin-Marktplatz. Keine öffentliche REST-API in 0.3.

## Voraussetzungen

- PHP 8.4+ mit `intl`, `pdo_mysql`, `gd`, `zip`, `sodium`, `curl`
- Composer
- MariaDB 10.11+ / 11.4 LTS (InnoDB, `utf8mb4`)
- Document Root = Projektordner (kein separates `public/`)

## Installation

### Web-Installer (empfohlen)

1. `composer install`
2. Document Root auf diesen Ordner zeigen (z. B. XAMPP: `http://localhost/nexis/`)
3. `/install` öffnen und Formular ausfüllen

Der Installer legt `.env`, Datenbank und Admin-Konto an und kann First-Party-Plugins aktivieren. Installer und Standardinhalte sind **deutsch und englisch**; die Standard-Sprache ist wählbar.

### CLI (Entwicklung)

1. `composer install`
2. `.env.example` nach `.env` kopieren, DB-Zugangsdaten und einen zufälligen `APP_KEY` setzen
3. Datenbank anlegen: `CREATE DATABASE nexis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
4. `php bin/migrate.php`
5. Optional: `php bin/seed.php` (Demo-Website und Konten; Passwörter in der Ausgabe)

## Nach der Installation

| URL | Zweck |
|---|---|
| `/admin/login` | Admin (UI-Sprache umschaltbar) |
| `/` | Öffentliche Site (Prefix der Standard-Locale) |
| `/account` | Kundenkonto (Login, Registrierung, Passwort-Reset) |
| `/admin/plugins` | Plugins, ZIP-Upload |
| `/admin/plugins/marketplace` | Plugin-Marktplatz |
| `/admin/theme` | Theme und Branding |
| `/admin/settings` | Website- und Spracheinstellungen |
| `/admin/about` | Version und Update-Check |
| `/health` | JSON-Healthcheck |

First-Party-Plugins: Forms, Redirects, Consent, Blog, Katalog.  
Themes: **Nexis** (Install-Default), Atelier, Editorial, Nord.

### Betrieb

```bash
php bin/queue-work.php   # Webhooks, Mail, geplante Publishes (Cron empfohlen)
composer backup          # DB-Dump + Medien → storage/backups/
```

Go-Live: [`docs/ops/go-live.md`](docs/ops/go-live.md) · Queue: [`docs/ops/queue.md`](docs/ops/queue.md)

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
Änderungen: [`CHANGELOG.md`](CHANGELOG.md).

## Lizenz

**[GNU GPL v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html)** (`GPL-2.0-or-later`).  
Volltext: [`LICENSE`](LICENSE) · Drittanbieter: [`THIRD_PARTY_NOTICES.md`](THIRD_PARTY_NOTICES.md).

Copyright (C) 2026 Vayvolt · [vayvolt.de](https://www.vayvolt.de)
