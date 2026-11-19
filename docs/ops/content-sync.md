# Content-Sync (Staging ↔ Production)

Inhalt von Staging nach Production bringen **ohne** Full-DB-Dump. Paketformat: `nexis.site.export` (aktuell Version 3).

## Was im ZIP steckt

- `site.json` — Site-Meta, Locales, Theme-Zuweisung + Overrides, Settings, Menüs, Redirects, Seiten (inkl. Entwürfe), Medien-Metadaten
- `media/{disk_key}` — Binärdateien (v3); ältere v2-Archive nutzen `media/{basename}`

**Nicht** enthalten: Benutzer, Rollen, API-Tokens, Webhooks, Plugin-/Theme-Pakete, Audit, Jobs.

Abgrenzung zum Backup: [restore.md](restore.md) sichert die ganze Datenbank. Content-Sync ist bewusst schmal für Redaktions-Workflows.

## Admin-UI

Unter **System → Export** (`/admin/export`), Recht `export.manage`:

1. Auf Staging: **ZIP herunterladen**
2. Auf Production: ZIP hochladen
3. Optional **Nur prüfen (Dry-Run)** — zählt create/update/skip ohne Schreibvorgänge

Bestehende Medien mit gleicher ID werden bei geändertem Inhalt ersetzt; unveränderte Checksums werden übersprungen.

## CLI

```bash
# Staging
php bin/export-site.php
php bin/export-site.php --out=/tmp/site-export.zip
# oder: composer export-site

# Production — zuerst prüfen
php bin/import-site.php /tmp/site-export.zip --dry-run

# Production — wirklich schreiben
php bin/import-site.php /tmp/site-export.zip --yes
# oder: composer import-site -- /tmp/site-export.zip --yes
```

`--yes` ist für echte Imports Pflicht. Actor ist der älteste Plattform-Admin.

## Typischer Ablauf

```text
Staging: Inhalte pflegen → Export-ZIP
     ↓
Production: Dry-Run → Import --yes → Smoke-Test Startseite / Admin
```

Settings-Secrets werden mit dem **APP_KEY der Zielinstallation** geschrieben. Domain/Locale-Hosts aus dem Export können Production-Domains überschreiben — nach dem Import Domains prüfen.

## Siehe auch

- Admin-Backup: [restore.md](restore.md)
- Sicherheit Document Root: [../06-sicherheit-und-betrieb.md](../06-sicherheit-und-betrieb.md)
