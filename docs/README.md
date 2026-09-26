# Dokumentation

Dokumentation für **Endanwender/Betrieb** und **Entwickler** (Plugin-/Theme-Verträge, Architektur).

## Lesereihenfolge

### Grundlagen

| # | Dokument | Inhalt |
|---|---|---|
| 01 | [Produktvision und Ziele](01-produktvision-und-ziele.md) | Nutzen, Abgrenzung, Qualitätsziele |
| 02 | [Systemarchitektur](02-systemarchitektur.md) | Schichten, Kernel, Laufzeit, Verzeichnislayout |
| 03 | [Datenmodell](03-datenmodell.md) | Entitäten, Beziehungen, JSON-Blöcke |
| — | [SQL-Referenzschema](../database/core_schema.sql) | Schema für Neuinstallationen |

### Erweiterbarkeit

| # | Dokument | Inhalt |
|---|---|---|
| 04 | [Plugin-System](04-plugin-system.md) | Lebenszyklus, Hooks, Verträge, Isolation |
| 05 | [Individualisierung und Theming](05-individualisierung-und-theming.md) | Tokens, Themes, Overrides, Slots |
| 10 | [Mehrsprachigkeit](10-mehrsprachigkeit.md) | Locales, URLs, Übersetzungsgruppen, LocalizedMap, Admin-UI |
| 11 | [Plugin-Marktplatz](11-plugin-marktplatz.md) | Directory-API, CMS Browse/Install/Update |

### Sicherheit und Betrieb

| # | Dokument | Inhalt |
|---|---|---|
| 06 | [Sicherheit und Betrieb](06-sicherheit-und-betrieb.md) | Auth, Tenancy, Hardening, Backup |
| — | [Go-Live-Checkliste](ops/go-live.md) | Produktion vor dem Start |
| — | [Queue-Betrieb](ops/queue.md) | Cron / systemd für Jobs |
| — | [nginx-Beispiel](ops/nginx.conf.example) | Document-Root-Hardening |
| — | [Restore](ops/restore.md) | Backup einspielen |

### APIs, Qualität und Entscheidungen

| # | Dokument | Inhalt |
|---|---|---|
| 07 | [API und Schnittstellen](07-api-und-schnittstellen.md) | Admin-API, Public-API, Events |
| 08 | [Entwicklungsstandards](08-entwicklungsstandards.md) | PHP 8.4, Qualität, Git, Tests |
| 09 | [Glossar und offene Entscheidungen](09-glossar-und-offene-entscheidungen.md) | Begriffe, offene Punkte |
| — | [Architecture Decision Records](adr/README.md) | Begründete Architekturentscheidungen |
| — | [CHANGELOG](../CHANGELOG.md) | Versionshistorie |

## Geltungsbereich

- **Verbindlich:** Architekturprinzipien, Plugin-Verträge, Sicherheitsregeln, Datenmodell-Kern.
- **Leitlinie:** UI-Details des Builders, konkrete Third-Party-Bibliotheken, Hosting-Varianten.
- **Nicht enthalten:** Marketingtexte, rechtliche AGB, Design-Mockups.

## Änderungsregeln

Architektur- und Vertragsänderungen (Plugin-Manifest, Event-Namen, Tabellenkern) werden versioniert und im Changelog der jeweiligen Datei festgehalten. Breaking Changes brauchen eine Migrationsstrategie.
