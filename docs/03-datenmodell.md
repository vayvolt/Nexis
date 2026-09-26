# 03 Datenmodell

MariaDB ist das System of Record. Der Kern speichert relationale Fakten; der Builder speichert den Seiteninhalt als versioniertes JSON-Dokument plus einen veröffentlichten Snapshot.

## 3.1 Konventionen

- Engine: **InnoDB**
- Zeichensatz: **utf8mb4**, Collation `utf8mb4_unicode_ci`
- Primärschlüssel: **UUID v7** als `CHAR(36)`
- Zeitstempel: `DATETIME(3)` in UTC, Spalten `created_at`, `updated_at`
- Soft-Delete nur wo Wiederherstellung fachlich nötig ist (`deleted_at`)
- JSON-Spalten nur für dokumentartige Daten (Blockbaum, Token-Overrides), nie für Fremdschlüssel
- Jede inhaltliche Tabelle trägt `site_id` (eine Website pro Installation; siehe ADR 008), außer globale Kataloge (`users` global, Zuordnung über `site_memberships`)

## 3.2 Kernentitäten

```text
tenants 1──1 sites 1──* site_locales
                 │
                 ├──* pages (locale, translation_group_id)
                 │         1──* page_revisions
                 │         1──* page_editorial_items (Kommentare / Aufgaben)
                 │         └── 1 page_snapshots
                 ├──* menus (pro Locale)
                 ├──* media_assets
                 ├──* plugin_installations
                 └──* site_settings

users 1──* site_memberships *──1 sites
roles 1──* role_permissions
```

### Installation und Site

Eine Installation hat genau **eine** aktive Website (`sites`). Die Tabelle `tenants` bleibt als organisatorische Hülle (1:1 zur Site), nicht als Multi-Mandanten-Produkt. Domain-Aliase liegen in `site_domains`.

Eine Site hat genau ein aktives Theme, beliebig viele aktivierte Plugins, eine Default-Locale und optional weitere Locales (`site_locales`). Die URL-Strategie (`prefix`, `domain`, `none`) steht an der Site. Details: [10 Mehrsprachigkeit](10-mehrsprachigkeit.md).

### Page

Eine Page ist ein navigierbarer Inhalt. Felder im Kern:

- `id`, `site_id`, `parent_id` (Seitenbaum, immer dieselbe Locale)
- `translation_group_id` (gemeinsame UUID aller sprachlichen Fassungen)
- `type` (`page`, `post`, `landing`, `system`, plugin-erweiterbar; Blog nutzt `post`)
- `slug`, `path` (materialisierter Pfad **ohne** Locale-Prefix)
- `status` (`draft`, `scheduled`, `published`, `in_review`, `archived`)
- `scheduled_at`, `scheduled_by` (bei Status `scheduled`; Veröffentlichung über `php bin/queue-work.php`)
- `unpublish_at`, `unpublish_by` (optionale geplante Offline-Schaltung veröffentlichter Seiten über denselben Worker)
- `locale`
- `published_revision_id`
- SEO-Felder (`meta_title`, `meta_description`, `canonical_url`, `robots`, `focus_keyword`)

`path` speichert den locale-freien Rest (`ueber-uns`). Prefix oder Domain kommen zur Laufzeit aus der URL-Strategie. Unique: `(site_id, locale, path)` und `(site_id, translation_group_id, locale)`.

Der Blockbaum liegt **nicht** in `pages`, sondern in Revisionen. Redaktionsnotizen und Aufgaben liegen in `page_editorial_items` (nur Admin, pro Seitenfassung).

### PageRevision

Jede Speicherung im Builder erzeugt eine Revision:

- `document` JSON – Editor-Zustand (Blöcke, Breakpoints, Draft-Flags)
- `schema_version` – für Migrationen des Blockformats
- `created_by`, `message`

### PageSnapshot

Beim Veröffentlichen entsteht ein **immutable Snapshot**:

- gerenderte Blockliste in kanonischer Form
- aufgelöste Medien-URLs zum Zeitpunkt der Veröffentlichung
- Hash für Cache-Invalidierung

Preview nutzt die aktuelle Revision. Live nutzt ausschließlich den Snapshot.

## 3.3 Blockdokument

Kanonisches JSON (vereinfacht):

```json
{
  "schemaVersion": 1,
  "root": {
    "id": "0193f0a0-7c2a-7e11-9c00-5f3c1a9b0001",
    "type": "core/section",
    "props": { "width": "wide", "padding": "lg" },
    "style": { "md": { "padding": "xl" } },
    "children": [
      {
        "id": "0193f0a0-7c2a-7e11-9c00-5f3c1a9b0002",
        "type": "core/heading",
        "props": { "text": "Willkommen", "level": 1 }
      },
      {
        "id": "0193f0a0-7c2a-7e11-9c00-5f3c1a9b0003",
        "type": "forms/contact",
        "props": { "formId": "0193f0aa-...." }
      }
    ]
  }
}
```

Regeln:

- `type` ist `{vendor}/{name}` und muss in der Block-Registry existieren.
- `props` werden gegen das JSON-Schema des Blocks validiert.
- Unbekannte Blocktypen bleiben im Dokument, rendern aber als Fallback.
- Medien werden als Asset-ID referenziert, nie als nackte Dateipfade.

## 3.4 Plugins und Themes in der Datenbank

`plugins` ist der Katalog (was liegt im Dateisystem).  
`plugin_installations` ist der Site-Zustand (aktiviert, Konfiguration, Status).

`themes` analog. Aktives Theme hängt an `sites.theme_id`. Token-Overrides liegen in `site_theme_overrides` (JSON), nicht in Theme-Dateien.

## 3.5 Menüs

`menus` (Site, Handle, Locale) und `menu_items` (Label, optional `page_id` oder externe `url`, `sort_order`). Öffentlich rendert Handle `primary` im Theme-Header. Pflege unter `/admin/menus`.

## 3.6 Medien

`media_assets` speichert Original, optionalen `alt_text` und Metadaten. Ableitungen (WebP, Thumbnails) in `media_variants` (`handle`: `thumb`, `webp`). Öffentliche URLs: `/media/{id}`, `/media/{id}/thumb`, `/media/{id}/webp`. Dateien liegen im Storage unter `{site_id}/{yyyy}/{mm}/{uuid}.{ext}`.

## 3.7 Rechte

- `users` – globale Identität (CMS und Kundenkonten); optional `email_verified_at`
- `roles` – entweder systemweit oder site-spezifisch (`site_id` nullable); System-Slugs `admin`, `editor` (Redakteur), `seo`, `member`
- `permissions` – stabile Strings (`content.page.publish`, `plugin.install`)
- `site_memberships` – User ↔ Site ↔ Role
- `password_reset_tokens` – gehashte Reset-Tokens für `/account/password/*`

Rolle `member`: Site-Mitgliedschaft ohne CMS-Permissions (nur `/account`). Öffentliche Registrierung/Reset über `site_settings` (`auth.registration_enabled`, `auth.password_reset_enabled`).

Default-Deny: keine Permission, kein Zugriff. Plugins dürfen nur Permissions aus ihrem Namespace registrieren, z. B. `forms.export`.

## 3.8 Settings

Zwei Tabellen:

- `settings` – installationsweit (Maintenance, Mail-from)
- `site_settings` – pro Site, Plugin-Namespacing `plugin:{vendor}/{name}.{key}`

Werte sind JSON, Schema kommt vom registrierenden Modul. Geheimnisse (Keys mit `.token`, `.password`, `.secret`, `.api_key`, `.oauth`, …) werden mit `APP_KEY` via libsodium `secretbox` verschlüsselt (`encrypted=1`).

## 3.8 Indizes und Integrität

Pflichtindizes:

- `sites(primary_domain)` unique
- `pages(site_id, locale, path)` unique where not deleted
- `pages(site_id, translation_group_id, locale)` unique
- `page_revisions(page_id, created_at)`
- `plugin_installations(site_id, plugin_id)` unique
- `media_assets(site_id, created_at)`

Foreign Keys mit `ON DELETE RESTRICT` für Inhalte, `ON DELETE CASCADE` nur für reine abhängige Sätze (Memberships, Revisions). Sites werden nicht hart gelöscht, solange Pages existieren.

## 3.9 Migrationen

- Kernel-Schema: `database/core_schema.sql` (Frischinstall via Migrator/`/install`)
- Plugin-Migrationen unter `plugins/{vendor}/{name}/migrations/`
- Plugin-Migrator führt Plugin-SQL nur für aktivierte Installationen aus
- Jede Migration ist vorwärts und mit explizitem `down` versehen
- JSON-Schema-Version des Blockdokuments wird unabhängig von SQL-Migrationen hochgezählt

## 3.10 Volltext und Suche

Phase 1: MariaDB `FULLTEXT` auf `pages.meta_title`, `pages.search_text`.  
`search_text` ist eine materialisierte, von Tags befreite Extraktion aus dem Snapshot, aktualisiert beim Publish.

Phase 2 optional: externe Engine, hinter demselben `SearchPort`.

Das physische Schema für Neuinstallationen steht in [database/core_schema.sql](../database/core_schema.sql).
