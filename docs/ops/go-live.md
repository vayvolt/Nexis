# Go-Live-Checkliste (Nexis 0.4)

Vor dem öffentlichen Betrieb abhaken. Details: [Sicherheit und Betrieb](../06-sicherheit-und-betrieb.md), [nginx](nginx.conf.example), [Queue](queue.md), [Restore](restore.md).

## Umgebung

- [ ] Document Root = Projektroot; Deny-Regeln aktiv (Apache `.htaccess` oder [nginx](nginx.conf.example))
- [ ] HTTPS erzwungen (Zertifikat + Redirect)
- [ ] `.env`: `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `.env`: `APP_URL` mit `https://…` (ohne trailing slash außer Pfad-Prefix)
- [ ] `.env`: starker `APP_KEY` (nicht der Beispielwert)
- [ ] `.env`: `TRUSTED_PROXIES` nur mit IPs des Reverse Proxys (sonst leer)
- [ ] Datenbank-User nur mit Rechten auf die Nexis-DB
- [ ] `storage/` und `.env` nicht öffentlich erreichbar (Smoke: `/storage/`, `/.env` → 403)

## Mail und Jobs

- [ ] `MAIL_TRANSPORT=smtp` (oder `mail`) mit gültigem From
- [ ] Queue-Worker läuft dauerhaft oder per Cron ([queue.md](queue.md))
- [ ] Testmail über Admin → Mail-Log prüfen (Status `sent`, SMTP-Protokoll bei Fehlern)
- [ ] Bei HTML-Benachrichtigungen: Client zeigt multipart korrekt (nicht nur Text-Fallback)

## Sicherheit

- [ ] Admin-Passwort stark; 2FA unter `/admin/security` aktiv
- [ ] Optional: `PLUGIN_TRUST_PUBLIC_KEY` setzen, wenn nur signierte Plugin-ZIPs erlaubt sein sollen
- [ ] Consent/GA: CSP wird automatisch erweitert, wenn Google-IDs konfiguriert sind
- [ ] Backup-Job eingerichtet: Planer unter `/admin/backups` aktiviert (läuft mit `php bin/queue-work.php`) **oder** Cron auf `php bin/backup.php --scheduled`; Restore einmal getestet (`php bin/restore.php --latest --yes` auf Staging)
- [ ] Vor CMS-ZIP-Update immer Backup; bei Fehlern Restore, nicht „weiterprobieren“

## Smoke-Test

- [ ] `/` bzw. Default-Locale lädt
- [ ] `/admin/login` funktioniert
- [ ] Seite veröffentlichen → öffentliche URL zeigt Inhalt; Cache-Header plausibel
- [ ] Medien-Upload und Auslieferung
- [ ] Formular-Submit (falls Plugin aktiv)
- [ ] `/health` → DB, `storage` und Queue-Status ok
- [ ] Optional: Admin → System → Health prüfen (Queue-Tiefe, Disk, PHP)

## Scope 0.4

- HTML-Admin-CMS mit Themes (Atelier, Editorial, Nord, …) und First-Party-Plugins
- Admin-/Public-GUI-i18n (de/en); Listen mit Übersetzungsgruppen; Menüs mit Locale-Tabs
- REST-API / API-Tokens: lesend + schreibend (Public + Admin), Tokens unter `/admin/api-tokens` (siehe [07 API](../07-api-und-schnittstellen.md)); CORS für Browser-Clients optional offen
- Backups unter `/admin/backups` (Planer, Download); Restore derzeit CLI (`php bin/restore.php`)
- Rollen-Matrix unter `/admin/roles`; Formular-Builder in `nexis/forms`
- Plugin-/Theme-Marktplatz (freie Pakete); Bezahlplugins: nicht Bestandteil
