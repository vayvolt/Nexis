# Go-Live-Checkliste (Nexis 0.3)

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
- [ ] Backup-Job eingerichtet (`php bin/backup.php`); Restore einmal getestet

## Smoke-Test

- [ ] `/` bzw. Default-Locale lädt
- [ ] `/admin/login` funktioniert
- [ ] Seite veröffentlichen → öffentliche URL zeigt Inhalt; Cache-Header plausibel
- [ ] Medien-Upload und Auslieferung
- [ ] Formular-Submit (falls Plugin aktiv)
- [ ] `/health` → DB, `storage` und Queue-Status ok
- [ ] Optional: Admin → System → Health prüfen (Queue-Tiefe, Disk, PHP)

## Scope 0.3 (ehrlich kommunizieren)

- HTML-Admin-CMS mit Themes (Atelier, Editorial, Nord) und First-Party-Plugins
- Admin-/Public-GUI-i18n (de/en); Listen mit Übersetzungsgruppen; Menüs mit Locale-Tabs
- **Keine** öffentliche REST-API / API-Tokens in 0.3 (siehe [07 API](../07-api-und-schnittstellen.md))
- Plugin-Marktplatz / Bezahlplugins: nicht Bestandteil
