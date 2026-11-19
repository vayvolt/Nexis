# Nexis

**Nexis** is a product by **[Vayvolt](https://www.vayvolt.de)** — a website builder on **PHP 8.4+** and **MariaDB**.  
One installation manages **one website**. Themes, branding, and plugins extend look and features.

- Portal & downloads: [nexis.vayvolt.de](https://nexis.vayvolt.de/)
- Source: [github.com/vayvolt/Nexis](https://github.com/vayvolt/Nexis)

German: [README.md](README.md)

Current **0.8.0**: block builder, admin/public i18n (de/en), first-party plugins, optional plugin/theme marketplace, **content sync** staging↔production (ZIP + CLI), **SSO / OpenID Connect**, **passkeys**, **user invites**, **trash**, light **page hierarchy**, backups including restore UI, unified cron (CLI + web), API CORS, role matrix, launch hardening (legal seeds, consent, a11y, retention, DSAR export). The REST API (`/api/v1`) reads and writes the core (site, pages, translations, builder document with `If-Match`, publish/revert, media upload, plugin status, theme tokens, block registry) including API tokens.

## Requirements

- PHP 8.4+ with `intl`, `pdo_mysql`, `gd`, `zip`, `sodium`, `curl`
- Composer
- MariaDB 10.11+ / 11.4 LTS (InnoDB, `utf8mb4`)
- Document root = project folder (no separate `public/`)

## Installation

### Web installer (recommended)

1. `composer install`
2. Point the document root at this folder (e.g. `http://localhost/nexis/`)
3. Open `/install` and complete the form

The installer creates `.env`, the database, and an admin account, and can enable first-party plugins. Installer and default content are **German and English**; the default language is selectable.

### CLI (development)

1. `composer install`
2. Copy `.env.example` to `.env`, set DB credentials and a random `APP_KEY`
3. Create the database: `CREATE DATABASE nexis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
4. `php bin/migrate.php`
5. Optional: `php bin/seed.php` (demo site and accounts; passwords are printed)
6. Existing installs without terms/imprint/privacy: `php bin/seed-legal-pages.php` (or Admin → Pages)

## After installation

| URL | Purpose |
|---|---|
| `/admin/login` | Admin (UI language switchable) |
| `/` | Public site (default locale prefix) |
| `/account` | Customer account (login, registration, password reset) |
| `/admin/plugins` | Plugins, ZIP upload |
| `/admin/plugins/marketplace` | Plugin marketplace |
| `/admin/theme` | Theme and branding |
| `/admin/settings` | Site, language, and SSO settings |
| `/admin/pages` | Pages including trash and hierarchy |
| `/admin/about` | Version and update check |
| `/health` | JSON health check |

First-party plugins (release): Forms, Redirects, Consent, Blog, Catalog.  
Themes (release): **Nexis** (install default), Atelier, Atelier Warm, Editorial.

### Operations

```bash
php bin/cron.php         # Queue, publish, retention, backup schedule (one cron line)
# Hosts without CLI: cron.php?key=… + CRON_SECRET in .env (docs/ops/cron.md)
php bin/export-site.php  # Content ZIP staging→prod (docs/ops/content-sync.md)
php bin/import-site.php archive.zip --dry-run|--yes
composer backup          # DB dump + media → storage/backups/
composer restore         # List / restore backups (--latest --yes)
```

Go-live: [`docs/ops/go-live.md`](docs/ops/go-live.md) | Queue: [`docs/ops/queue.md`](docs/ops/queue.md)

## For developers

```bash
composer test
composer analyse
```

Stack: PSR-4/7/11/14/15, PHP views (admin), Twig (public themes), JSON block schema (builder).

```text
nexis/                 # Document root
├── index.php          # Front controller
├── src/               # Core
├── plugins/           # Plugins
├── themes/            # Themes
├── resources/         # Lang, admin views
├── database/          # core_schema.sql
├── docs/              # Architecture and contracts
└── tests/
```

Docs: [`docs/README.md`](docs/README.md) — architecture, data model, plugins, theming, i18n, security/ops, API, marketplace, ADRs.  
Changelog: [`CHANGELOG.md`](CHANGELOG.md) | English: [`CHANGELOG.en.md`](CHANGELOG.en.md).

## License

**[GNU GPL v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html)** (`GPL-2.0-or-later`).  
Full text: [`LICENSE`](LICENSE) | Third parties: [`THIRD_PARTY_NOTICES.md`](THIRD_PARTY_NOTICES.md).

Copyright (C) 2026 Vayvolt | [vayvolt.de](https://www.vayvolt.de)
