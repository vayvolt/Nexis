# Queue-Worker (Betrieb)

Nexis verarbeitet Webhooks, Mail-Jobs, **plugin-registrierte Queues** und geplante Veröffentlichungen über die DB-Queue.

```bash
php bin/queue-work.php
```

Das Script bootet aktivierte Plugins (damit `JobHandler` / Mail-Typen greifen), verarbeitet bis zu 50 Jobs je Typ und **beendet sich danach**. Ohne regelmäßigen Lauf bleiben geplante Publishes, Webhooks, SMTP- und Plugin-Jobs liegen.

## Cron (empfohlen, einfach)

Jede Minute:

```cron
* * * * * cd /var/www/nexis && /usr/bin/php bin/queue-work.php >> storage/logs/queue-cron.log 2>&1
```

## systemd timer

`/etc/systemd/system/nexis-queue.service`:

```ini
[Unit]
Description=Nexis queue worker (one shot)
After=network.target mysql.service

[Service]
Type=oneshot
User=www-data
Group=www-data
WorkingDirectory=/var/www/nexis
ExecStart=/usr/bin/php bin/queue-work.php
ProtectSystem=full
PrivateTmp=true
```

`/etc/systemd/system/nexis-queue.timer`:

```ini
[Unit]
Description=Run Nexis queue worker every minute

[Timer]
OnBootSec=30s
OnUnitActiveSec=60s
AccuracySec=5s
Unit=nexis-queue.service

[Install]
WantedBy=timers.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now nexis-queue.timer
sudo systemctl list-timers | grep nexis
```

## Checks

- Admin → Mail-Log: neue Einträge nach Testversand
- HTML-Mails: `MailMessage::$htmlBody` erzeugt multipart/alternative (quoted-printable); Text-Fallback bleibt in `body_text` des Logs
- Geplante Seite: Status wechselt nach Fälligkeit zu veröffentlicht
- Webhook: Ziel-URL erhält POST inkl. `X-Nexis-Signature: sha256=<hmac-hex>`
- Nach 5 Fehlversuchen landen Jobs in `failed_jobs` (nicht still gelöscht)
- Parallel laufende Worker: Exit `75` bei Lock; sonst Exit `1` bei Fatal

Cron sollte Exit≠0 überwachen (`storage/logs/queue-cron.log`).
