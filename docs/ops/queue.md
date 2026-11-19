# Queue & Hintergrundjobs

Periodische Verarbeitung läuft über den **einheitlichen Cron**:

```bash
php bin/cron.php
```

Vorlage und Crontab: [cron.md](cron.md), [crontab.example](crontab.example).

Pro Lauf (bis zu 50 Jobs je Typ, danach Exit): Webhooks, Mail, plugin-registrierte Queues, geplante Veröffentlichung/Offline, Retention-Purge und Backup-Planer. Ohne regelmäßigen Cron bleiben Jobs und geplante Publishes liegen.

Purge-Fenster: Audit-Log (`AUDIT_RETENTION_DAYS`, Default 180) und Formular-Einreichungen (`FORMS_RETENTION_DAYS`, Default 365) — Details [retention.md](retention.md).

## systemd timer (optional)

`/etc/systemd/system/nexis-cron.service`:

```ini
[Unit]
Description=Nexis cron (one shot)
After=network.target mysql.service

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=/var/www/html/nexis
ExecStart=/usr/bin/php bin/cron.php
ProtectSystem=full
PrivateTmp=true
```

`/etc/systemd/system/nexis-cron.timer`:

```ini
[Unit]
Description=Run Nexis cron every minute

[Timer]
OnBootSec=30s
OnUnitActiveSec=60s
AccuracySec=5s
Unit=nexis-cron.service

[Install]
WantedBy=timers.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now nexis-cron.timer
sudo systemctl list-timers | grep nexis
```

## Checks

- Admin → Mail-Log: neue Einträge nach Testversand
- HTML-Mails: `MailMessage::$htmlBody` erzeugt multipart/alternative (quoted-printable); Text-Fallback bleibt in `body_text` des Logs
- Geplante Seite: Status wechselt nach Fälligkeit zu veröffentlicht
- Webhook: Ziel-URL erhält POST inkl. `X-Nexis-Signature: sha256=<hmac-hex>`
- Nach 5 Fehlversuchen landen Jobs in `failed_jobs` (nicht still gelöscht)
- Parallel laufende Läufe: Exit `75` bei Lock; sonst Exit `1` bei Fatal
- Backup-Planer aktiv: neue Sicherung unter Admin → Backups, „Letzter Lauf“ aktualisiert sich

Cron sollte Exit≠0 überwachen (`storage/logs/cron.log`).
