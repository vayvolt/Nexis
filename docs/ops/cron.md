# Cron (einheitlicher Einstieg)

Alle periodischen Aufgaben laufen über **eine** Logik (`CronRunner`):

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

## Web-Cron (Hoster ohne CLI)

Manche Hoster erlauben nur HTTP-Aufrufe. Dafür liegt im Projektroot `cron.php` (Apache: in `.htaccess` freigeschaltet).

1. In `.env` setzen:

```env
CRON_SECRET=langer-zufaelliger-string
```

2. Im Hoster-Panel z. B. jede Minute aufrufen:

```text
https://example.com/cron.php?key=langer-zufaelliger-string
```

Alternativ Header `X-Nexis-Cron-Key: langer-zufaelliger-string` (ohne Key in der URL).

Ohne `CRON_SECRET` antwortet der Endpunkt mit **403** (deaktiviert). Falscher Key ebenfalls **403**. Parallel laufend: **409**. Fehler: **500**. Erfolg: **200** (Text wie CLI-Ausgabe).

Unterordner-Installationen: Basispfad beachten, z. B. `https://example.com/nexis/cron.php?key=…`.

## systemd (optional)

Gleiche One-Shot-Logik wie bisher, `ExecStart` auf `bin/cron.php`:

```ini
ExecStart=/usr/bin/php bin/cron.php
```

Timer weiterhin jede Minute (`OnUnitActiveSec=60s`) — siehe früher `nexis-queue.timer` in [queue.md](queue.md).
