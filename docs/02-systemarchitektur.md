# 02 Systemarchitektur

## 2.1 Architekturstil

Nexis ist ein **modularer Monolith** mit Plugin-Kernel. Alle Module teilen sich einen Prozess und eine Datenbank, kommunizieren aber nur über Verträge (Interfaces, Events, Extension Points). Microservices sind für dieses Produkt unnötig und würden den Plugin-Alltag erschweren.

```text
┌─────────────────────────────────────────────────────────────┐
│                        Public / Admin                       │
│              PSR-15 Pipeline  ·  CSRF  ·  Auth              │
└─────────────────────────────┬───────────────────────────────┘
                              │
┌─────────────────────────────▼───────────────────────────────┐
│                         Application                         │
│   Site · Pages · Builder · Media · Users · Settings         │
└───────────────┬─────────────────────────────┬───────────────┘
                │                             │
┌───────────────▼───────────┐   ┌─────────────▼───────────────┐
│     Plugin Runtime        │   │     Theme Runtime           │
│  Manifest · Hooks · DI    │   │  Tokens · Twig · Slots      │
└───────────────┬───────────┘   └─────────────┬───────────────┘
                │                             │
┌───────────────▼─────────────────────────────▼───────────────┐
│                         Infrastructure                      │
│   MariaDB  ·  Filesystem  ·  Cache  ·  Queue  ·  Mail       │
└─────────────────────────────────────────────────────────────┘
```

## 2.2 Schichten

| Schicht | Verantwortung | Darf kennen |
|---|---|---|
| `Http` | Routing, Middleware, Controller | Application-Services |
| `Application` | Use-Cases, Transaktionen, Autorisierung | Domain, Ports |
| `Domain` | Seiten, Blöcke, Site, Plugins als Modelle | keine Infrastruktur |
| `Plugin` | Entdeckung, Boot, Isolation, Verträge | Domain-Events, Ports |
| `Theme` | Auflösung von Templates, Tokens, Assets | Domain-Read-Models |
| `Infrastructure` | MariaDB, Files, Cache, Mail | technische Details |

Abhängigkeiten zeigen nur nach innen. Plugins hängen am Kernel, nie umgekehrt.

## 2.3 Kernel

Der Kernel startet in dieser Reihenfolge:

1. Umgebungsvariablen und `config.php` laden
2. PSR-11 Container aufbauen
3. Installierte Plugins aus `plugin_installations` lesen
4. Manifeste validieren und kompatible Plugins registrieren
5. Plugin-Service-Provider booten (Routen, Events, Blöcke, Slots)
6. Theme der aktuellen Site laden
7. PSR-15 Pipeline ausführen

PHP 8.4 **Lazy Objects** werden für Plugin-Dienste genutzt: teure Services entstehen erst beim ersten Zugriff.

## 2.4 Document Root und Request-Typen

Der Document Root ist der **Projektroot** (`/`), nicht ein Unterordner `public/`. Das entspricht dem typischen XAMPP-Layout (`htdocs/nexis/`). Alle HTTP-Requests laufen über den Front Controller `index.php` im Root, sofern die Datei nicht als statisches Asset unter `assets/` existiert.

| Typ | Einstieg | Cache |
|---|---|---|
| Öffentliche Seite | `/index.php` (Rewrite auf `/`) | Full-Page-Cache nach Publish |
| Admin / Builder | Prefix `/admin` über denselben Front Controller | kein Full-Page-Cache |
| JSON-API | Prefix `/api/v1` (**geplant ab 0.2**) | selektiv, nie für Schreibzugriffe |
| Publizierte Assets | `/assets/…` (Dateien direkt) | lang, gehasht |

`.htaccess` (Apache/XAMPP) bzw. die Server-Config muss `src/`, `config/`, `vendor/`, `storage/`, `database/`, `tests/`, `docs/`, `plugins/`-PHP, Theme-Quellen und Dotfiles vom HTTP-Zugriff ausschließen. Details: [06 Sicherheit und Betrieb](06-sicherheit-und-betrieb.md#611-document-root-im-projektordner).

Jede öffentliche Seite wird aus dem **veröffentlichten Snapshot** gerendert, nicht live aus dem Editor-Dokument. Dadurch bleiben Preview und Live getrennt.

## 2.5 Seiten-Rendering

```text
URL → Site auflösen → Locale auflösen → Page finden → Snapshot laden
    → Blockbaum walken → Plugin-Block-Renderer
    → Theme-Layout + Slots (Language-Switcher aus alternate_urls)
    → HTML + hreflang + Asset-Manifest
```

Ein Block kennt nur sein Schema und seinen Renderer. Layout, Header und Footer kommen vom Theme. Plugins dürfen in deklarierte Slots injizieren, nicht beliebig ins Markup schreiben.

Locale-Auflösung, Übersetzungsgruppen und URL-Strategien: [10 Mehrsprachigkeit](10-mehrsprachigkeit.md).

## 2.6 Verzeichnislayout

```text
/                               # Document Root
├── index.php                   # Front Controller
├── .htaccess
├── assets/                     # nur publizierte, gehashte Dateien
├── config/                     # HTTP-Deny
├── src/                        # HTTP-Deny
│   ├── Kernel/
│   ├── Http/
│   ├── Auth/
│   ├── Site/
│   ├── Content/
│   ├── Builder/
│   ├── Media/
│   ├── Plugin/
│   ├── Theme/
│   ├── Settings/
│   └── Support/
├── plugins/{vendor}/{name}/    # PHP/HTTP-Deny, Assets nur über Pipeline
│   ├── plugin.json
│   ├── src/
│   ├── resources/views
│   ├── resources/lang
│   ├── resources/assets
│   └── migrations/
├── themes/{name}/              # HTTP-Deny
│   ├── theme.json
│   ├── tokens.json
│   ├── templates/
│   ├── lang/
│   └── assets/
├── storage/                    # HTTP-Deny, Medien über den Front Controller
├── vendor/                     # HTTP-Deny
└── database/                   # HTTP-Deny · core_schema.sql
```

## 2.7 Technologieentscheidungen

| Thema | Entscheidung | Begründung |
|---|---|---|
| Framework | Kein Full-Stack-Framework, Symfony-Komponenten wo sinnvoll | Plugin-API bleibt eigen, kein Vendor-Lock der App-Struktur |
| HTTP | PSR-7/15, z. B. `nyholm/psr7` + eigener Dispatcher | Testbar, middleware-first |
| DI | PSR-11, z. B. PHP-DI oder Symfony DI | Plugins registrieren Services über Provider |
| Events | PSR-14 | Entkopplung ohne God-Objects |
| Templates | Twig | Escaping default, Theme-Vererbung |
| Queue | Datenbank-Queue in Phase 1, später Redis | Weniger bewegliche Teile am Anfang |
| Mail | `MailPort` (`MAIL_TRANSPORT=log\|mail\|smtp`), Jobs in Queue `mail`, Protokoll in `mail_log` | SMTP-Secrets nur in `.env`; Admin → Mail-Log; optional `htmlBody` → multipart/alternative (quoted-printable, CRLF) |
| Cache | Datei oder Redis, Interface davor | Full-Page und Schema-Cache |
| IDs | UUID v7 als CHAR(36) | Sortierbar, migrationsfreundlich, API-tauglich |

## 2.8 PHP 8.4-Nutzung im Kern

- **Property Hooks** für abgeleitete Werte (`Page::$isPublished`, Token-Auflösung).
- **Asymmetric Visibility** für Aggregate (`public private(set) string $id`).
- **Lazy Objects** für Plugin- und Theme-Services.
- `array_find`, `array_any`, `array_all` in Registry-Lookups statt eigener Loops.

## 2.9 Laufzeitkonfiguration

Konfiguration ist dreistufig und überschreibt von unten nach oben:

1. Datei `config/app.php` (Defaults der Installation)
2. Umgebungsvariablen / `.env`
3. Site-Settings in MariaDB (nur ungefährliche, nicht-geheime Werte)

Geheimnisse (DB-Passwort, Mail-DSN, Signing-Keys) liegen **nie** in der Datenbank und **nie** in Plugin-Settings ohne Verschlüsselung.

## 2.10 Fehler- und Isolationsgrenzen

- Ungefangene Plugin-Exceptions im öffentlichen Render werden geloggt und der betroffene Block durch einen Fallback ersetzt.
- Boot-Fehler eines Plugins deaktivieren das Plugin für den Request und schreiben einen Health-Eintrag.
- Kernel-Fehler (DB down, Theme fehlt) führen zu einer kontrollierten Fehlerseite, nicht zu einem Stacktrace.

## 2.11 Abgrenzung Builder vs. CMS

Der Builder ist kein zweites CMS. Er ist die **Editor-Oberfläche** für das Content-Aggregat `Page`. Speichern schreibt ein Editor-Dokument. Veröffentlichen materialisiert einen Snapshot. Plugins erweitern Blocktypen, nicht den Speicher selbst.
