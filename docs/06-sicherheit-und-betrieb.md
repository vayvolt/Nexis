# 06 Sicherheit und Betrieb

Sicherheit ist Default, nicht Option. Nexis verarbeitet fremde Inhalte, Uploads und Plugin-Code – die Angriffsfläche ist bewusst eingegrenzt.

## 6.1 Bedrohungsmodell (kurz)

| Bedrohung | Gegenmaßnahme |
|---|---|
| Privilege Escalation im Admin | Default-Deny Policies, Session-Härte, CSRF |
| XSS über Blöcke und Custom CSS | Twig auto-escape, CSS-Sanitizer (kein `</style>`-Breakout / `@import`), CSP |
| SQL-Injection | ausschließlich Prepared Statements / Query-Builder |
| Unsichere Uploads | MIME/Extension-Whitelist, kein PHP im Media-Root |
| Bösartiges Plugin | Signatur, Staging, Isolation, kein `eval` |
| Session-Hijacking | `Secure`, `HttpOnly`, `SameSite=Lax` oder `Strict` im Admin |
| Datenleck über falsche Site-ID | `site_id` in jeder Query, Policy-Tests |
| CSRF auf Public-Forms | Token pro Formular-Plugin, Origin-Check |

## 6.2 Authentifizierung

- Session-basiert für Admin und Builder (keine JWTs in Cookies für den Editor).
- Passwort-Hash: `PASSWORD_ARGON2ID`.
- Login-Throttling pro Konto und IP.
- Optional TOTP als Second Factor.
- API-Tokens (persönlich, scoped, hash-gespeichert) für Automation: **geplant ab 0.2**, getrennt von der Browser-Session.

## 6.3 Autorisierung

Jede Application-Aktion ruft eine Policy auf. `SitePolicy::view` prüft Membership (oder Plattform-Admin). Schreibaktionen prüfen zusätzlich `SitePolicy::can(..., Permission::…)` über `role_permissions` (Default-Deny). Plattform-Admins umgehen Capability-Checks.

Site-Isolation: Ein User ohne Membership sieht die Site nicht, auch nicht per ID-Guessing (404 statt 403 auf öffentlichen Enumeration-Pfaden, 403 im Admin).

Kern-Capabilities (Auszug): `content.page.edit`, `content.page.seo`, `content.page.submit_review`, `content.page.publish`, `theme.manage`, `theme.custom_css`, `plugin.manage`, `settings.manage`, `users.manage`, `export.manage`, `webhooks.manage`, `audit.view`. System-Rollen-Templates out of the box: **Website-Admin** (`admin`), **Redakteur** (`editor`), **SEO** (`seo`), **Mitglied** (`member`). Plugin-Capabilities (z. B. `forms.manage`, `consent.manage`, `redirects.manage`) kommen aus dem Manifest/`registerPermissions` und werden beim Boot an konfigurierte Rollen vergeben (`provides.permissionRoles`, Default `admin`+`editor`; Redirects inkl. `seo`).

## 6.4 Content Security Policy

Default-Header der öffentlichen Site (Kern):

- `default-src 'self'`
- `script-src 'self'` – Plugins können Quellen über `CspContributor` ergänzen
- `style-src 'self' 'unsafe-inline'` plus Font-CDNs
- `img-src 'self' data: https:`
- `connect-src 'self'`
- `object-src 'none'`
- `base-uri 'self'`
- `frame-ancestors 'self'`

`nexis/consent` erweitert `script-src` / `connect-src` / `frame-src` nur bei konfigurierten Google-IDs (GA4/GTM/Ads) um googletagmanager.com und Analytics-Hosts (inkl. `'unsafe-inline'` für Consent-Mode-Bootstrap). Ohne Google-Integration: kein Banner, keine Consent-Skripte, CSP unverändert.

Inline-Scripts aus Blöcken sind verboten. Plugins, die JS brauchen, liefern Dateien über `/assets/…`.

## 6.5 Uploads und Medien

Erlaubt in Phase 1 / 0.2: `jpg`, `jpeg`, `png`, `webp`, `gif`, `pdf`.
SVG ist bewusst **nicht** in der Upload-Whitelist (XSS-Risiko); eine sanitisierte SVG-Option kann später kommen.

Document Root für Medien ist **nicht** `storage/`. Originale liegen unter `storage/media/` und werden nur über den Front Controller (Signatur, Rechte, MIME) ausgeliefert. PHP-Execution in `storage/` ist per `.htaccess` aus.

Maximale Größe und Pixelmaß sind über `MEDIA_MAX_BYTES` und `MEDIA_MAX_PIXELS` konfigurierbar (Defaults: 10 MiB / 25 MP). Bilder werden re-encodiert, um eingebettetes PHP in Polyglots zu zerstören.

## 6.6 Secrets und Konfiguration

| Secret | Speicher |
|---|---|
| Datenbank, Mail | `.env`, nicht versioniert |
| `APP_KEY` (Sodium) | `.env` |
| Plugin-OAuth-Tokens / Secrets in `site_settings` | encrypted via `SecretBox` (`APP_KEY`, libsodium) |
| Backup-Credentials | nur auf dem Host, nicht in der App |

`APP_DEBUG=false` in Production ist Pflicht. Fehlerseiten zeigen keine Stacktraces.

## 6.7 Betrieb

### PHP / Webserver

- Document Root = Projektroot (XAMPP: `htdocs/nexis/`)
- PHP 8.4+ FPM bzw. XAMPP-Apache mit `mod_rewrite` und `intl`
- `expose_php=Off`
- Session-Dateien außerhalb des Document Root (z. B. `storage/sessions/` plus Deny, besser außerhalb des Projekts)
- HTTPS verpflichtend hinter dem Reverse Proxy (`X-Forwarded-Proto` nur von `TRUSTED_PROXIES`)
- Session-Cookie: `HttpOnly`, `SameSite=Lax`, `Secure` wenn HTTPS erkannt (`APP_URL` oder Trusted Proxy)
- Go-Live: [ops/go-live.md](ops/go-live.md) · nginx: [ops/nginx.conf.example](ops/nginx.conf.example) · Queue: [ops/queue.md](ops/queue.md)

### MariaDB

- Eigener User, nur die Nexis-Datenbank
- Tägliche logische Backups (`mariadb-dump --single-transaction`) plus binlog oder tägliche Snapshots
- Restore-Test mindestens quartalsweise
- `sql_mode` inkl. `STRICT_TRANS_TABLES`

### Cache und Queue

- Full-Page-Cache wird bei `PagePublished`, Theme-Wechsel und Token-Änderung invalidiert.
- Plugins können den Full-Page-Cache pro Request überspringen (`PluginKernel::registerPageCacheBypass`, z. B. Warenkorb).
- Queue-Worker laufen per Cron oder systemd-Timer (`php bin/queue-work.php`: Webhooks, Mail, geplante Publishes). Siehe [ops/queue.md](ops/queue.md).
- Fehlgeschlagene Jobs nach 5 Versuchen in `failed_jobs` (Worker loggt `error`); Cron sollte Exit≠0 überwachen.

## 6.8 Backup- und Restore-Einheiten

Ein vollständiges Backup umfasst:

1. MariaDB-Dump
2. `storage/` (Medien, Plugin-Storage)
3. `plugins/` und `themes/` (falls nicht aus Git/Artefakt)
4. `.env` getrennt und verschlüsselt

Restore ist Site-übergreifend. Site-Export/Import (ZIP unter `/admin/export`) deckt Seiten, Medien, Menüs, Redirects, Theme-Overrides und Settings ab.

## 6.9 Audit

Geschrieben werden u. a.:

- Login Erfolg/Fehlschlag
- Publish / Revert
- Plugin install/enable/disable/uninstall
- Rechteänderungen
- Settings-Änderungen an Secrets (Wert nicht im Klartext loggen)

Retention: Default 180 Tage (Purge über `php bin/queue-work.php`; derzeit fest im Worker, nicht per Env konfigurierbar).

## 6.10 DSGVO-relevante Punkte

- **Cookies / TTDSG:** Technisch notwendige Cookies (Session, CSRF, Sicherheit) erfordern keine Einwilligung; sie müssen in der Datenschutzerklärung beschrieben sein. Statistik/Marketing (Google Analytics, GTM, Ads) nur nach Opt-in – das Consent-Plugin zeigt das Banner **nur**, wenn mindestens eine Google-ID konfiguriert ist.
- Medien und Formular-Plugins speichern personenbezogene Daten site-bezogen.
- Löschkonzept: User-Anonymisierung beim Soft-Delete (E-Mail/Name/TOTP), Formular-Export (CSV) und Löschung als Plugin-Pflicht; Formular-Retention-Purge über Queue-Worker (365 Tage).
- Auftragsverarbeitung liegt außerhalb dieser technischen Doku, muss aber zu den Löschpfaden passen.

## 6.11 Document Root im Projektordner

Weil der Webserver den Projektroot ausliefert, ist **Deny-by-Default** für alles außer Front Controller und publizierten Assets Pflicht. Das ist kein optionales Hardening, sondern Teil der Installation.

Web-sichtbar:

- `index.php`
- `assets/` (nur gehashte CSS/JS/Fonts/Bilder)

HTTP 403 bzw. nicht auffindbar:

- `src/`, `config/`, `vendor/`, `database/`, `tests/`, `docs/`, `bin/`, `resources/`
- `plugins/` und `themes/` (Quelldateien; publizierte Kopien liegen in `assets/`)
- `storage/` inklusive Medien, Logs, Cache
- `.env`, `composer.json`, `composer.lock`, `.git/`

Apache/XAMPP (Root-`.htaccess`, Kernregeln – Reihenfolge bindend):

```apache
RewriteEngine On

# 1) Geschützte Bäume
RewriteRule ^(src|config|vendor|storage|database|tests|docs|plugins|themes|bin|resources)(/|$) - [F]

# 2) Keine PHP-Datei außer dem Front Controller
RewriteCond %{REQUEST_URI} \.php$ [NC]
RewriteCond %{REQUEST_URI} !^/index\.php$ [NC]
RewriteRule ^ - [F]

# 3) Front Controller; existierende Dateien unter assets/ durchlassen
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

In denselben geschützten Ordnern zusätzlich `Require all denied` (Apache 2.4), falls Rewrite umgangen wird. Dotfiles (`.env`, `.git`) per `RewriteRule "(^|/)\." - [F]`.

nginx-Äquivalent: `root` auf den Projektordner, `location /` mit `try_files $uri /index.php?$query_string;`, `location ~* /(src|config|vendor|storage|database|tests|docs|bin|plugins|resources|themes)/ { deny all; }` bzw. Theme-Quelldateien gezielt sperren (siehe [nginx.conf.example](ops/nginx.conf.example)), PHP nur für `index.php`.

Medien-URLs haben die Form `/media/{assetId}/{variant}` und laufen durch PHP, nicht als statischer Alias auf `storage/`.

