# Cron (einheitlicher Einstieg)

Alle periodischen Aufgaben laufen über **eine** Datei:

```bash
php bin/cron.php
# oder
composer cron
```

Pro Lauf: Queue (Webhooks, Mail, Plugin-Jobs), geplante Publish/Unpublish, Retention-Purge (Audit, Idempotency, Forms) und der Backup-Planer aus `/admin/backups`.

Vorlage zum Eintragen: [`crontab.example`](crontab.example).

```cron
* * * * * cd /var/www/html/nexis && /usr/bin/php bin/cron.php >> storage/logs/cron.log 2>&1
```

Separates `php bin/backup.php --scheduled` ist nur nötig, wenn **kein** Cron auf `bin/cron.php` läuft.

Details zu Queue/Checks: [queue.md](queue.md). Backup-Planer: [restore.md](restore.md). Retention: [retention.md](retention.md).

## systemd (optional)

Gleiche One-Shot-Logik wie bisher, `ExecStart` auf `bin/cron.php`:

```ini
ExecStart=/usr/bin/php bin/cron.php
```

Timer weiterhin jede Minute (`OnUnitActiveSec=60s`) — siehe früher `nexis-queue.timer` in [queue.md](queue.md).
