# Backup & Restore

Ein Backup aus dem Admin, von `php bin/backup.php` (oder `composer backup`) liegt unter `storage/backups/{timestamp}/` und enthält:

- `database.sql` – logischer MariaDB-Dump (`--single-transaction`)
- `media/` – Kopie von `storage/media/` (abwählbar)
- `manifest.json` – Metadaten

Schlägt das Backup fehl (kein `mysqldump`, leerer Dump, Medien-Fehler), wird das unvollständige Verzeichnis **verworfen** — damit kein kaputter Stand als Restore-Quelle dient.

Unter XAMPP/Windows sucht das Skript `mysqldump`/`mysql` im PATH und unter `…\xampp\mysql\bin\`. Optional: `MYSQLDUMP_PATH` / `MYSQL_PATH` in `.env` bzw. Umgebung setzen.

`.env` und Secrets gehören **nicht** ins Backup-Archiv; getrennt und verschlüsselt sichern. Der Admin-Download filtert `.env`-Dateien zusätzlich aus dem ZIP heraus.

## Backups im Admin (`/admin/backups`)

Recht `settings.manage`, System → Backups:

- **Liste** aller Stempel mit Zeit (UTC), Größe, vorhandenen Bestandteilen (`database.sql`, `media/`) und Datenbankname aus dem Manifest
- **Jetzt sichern** — Datenbank immer, Medien optional. Liegt das letzte Backup weniger als eine Minute zurück, wird der Lauf abgelehnt (Doppelklick-Schutz).
- **Download** als ZIP des gesamten Stempels oder nur `database.sql`
- **Löschen** eines Stempels (mit Bestätigung)

Ein Restore läuft weiterhin bewusst über die Konsole (`php bin/restore.php`) — destruktive Schritte gehören nicht hinter einen Browser-Klick.

### Planer

Im selben Screen; gespeichert als Site-Settings `backup.schedule.enabled`, `.interval` (`daily`/`weekly`), `.retain` (1–60) und `.include_media`, dazu `backup.schedule.last_run` als Zeitstempel des letzten Laufs.

Ist der Planer fällig, wird ein Backup erstellt und **alle Stempel jenseits der Aufbewahrung** werden gelöscht (ältester zuerst). Verzeichnisse, die nicht dem Stempelmuster `YYYYMMDD-HHMMSS` entsprechen, bleiben unangetastet. Manuelle Backups über die UI löschen nie automatisch.

Ausgeführt wird der Planer an zwei Stellen — beide sind durch `storage/cache/backup-schedule.lock` gegen parallele Läufe abgesichert:

```bash
# a) automatisch am Ende jedes Queue-Worker-Laufs
php bin/queue-work.php

# b) eigener Cron-Job (ohne Queue-Worker)
php bin/backup.php --scheduled
```

Beide Varianten sind idempotent: Ist nichts fällig, passiert nichts (Exit 0). Ein Cron-Intervall unterhalb des Planer-Intervalls ist also unproblematisch — empfohlen sind stündliche Aufrufe.

`backup.schedule.last_run` protokolliert den **Versuch**, nicht den Erfolg: Ein fehlgeschlagenes Backup (fehlendes `mysqldump`, volle Platte) landet im Log und wird erst im nächsten Intervall erneut probiert, statt bei jedem Worker-Lauf. Nach dem Beheben der Ursache hilft „Jetzt sichern“ in der UI. Existiert noch kein `last_run`, zählt das neueste vorhandene Backup als letzter Lauf — ein frisch aktivierter Planer verdoppelt ein gerade erstelltes Backup also nicht.

Ein einzelnes Backup ohne Planer-Logik erzeugt weiterhin `php bin/backup.php`, optional mit `--no-media`.

## Vor einer Neuinstallation (Web-Installer)

Wenn „Datenbank leeren“ aktiv ist, legt der Installer **vor** dem Wipe ein Backup an. Schlägt die Installation danach fehl, wird dieses Backup automatisch zurückgespielt. Schlägt das Backup selbst fehl, wird **nicht** geleert.

## Vor einem CMS-Update

### Im Admin (ohne Konsole)

Unter **Über Nexis** (`/admin/about`), Recht `settings.manage`:

1. „Nach CMS-Update suchen“
2. „Jetzt auf v… upgraden“ bestätigen

Ablauf intern: Wartungsmodus → DB-Backup + Code-Snapshot der ersetzten Pfade → ZIP vom Katalog → Core-Dateien ersetzen (`.env`/`storage/` bleiben) → `Migrator` (nur bei Upgrade) → Cache leeren. Bei Fehler: Code aus Snapshot + DB aus Backup zurück; Wartungsmodus geht wieder aus, sofern der Rollback geklappt hat (sonst bleibt er an).

Ältere Katalog-Versionen können unter demselben Screen manuell gewählt werden (**Code-Downgrade**). Das Datenbankschema wird dabei nicht zurückgerollt.

### Manuell (ZIP + CLI)

1. Wartungsmodus (optional) unter Einstellungen → Betrieb.
2. Backup: `php bin/backup.php` oder „Jetzt sichern“ unter `/admin/backups` — bei Fehler **nicht** updaten.
3. Neues CMS-ZIP vom Katalog installieren / Dateien ersetzen.
4. Bei Problemen zurückrollen: `php bin/restore.php --latest --yes` (oder konkreten Stempel).
5. Wartungsmodus aus, Smoke-Test.

## Restore per Skript (empfohlen)

```bash
# Backups auflisten
php bin/restore.php

# Neuestes Backup (DB + Medien), Page-Cache wird geleert
php bin/restore.php --latest --yes

# Bestimmtes Backup, nur Datenbank
php bin/restore.php 20260928-091500 --db-only --yes
```

`--yes` ist Pflicht (destruktiv). Ohne Flag bricht das Skript ab.

## Restore manuell (Staging)

1. Leere Zieldatenbank anlegen (oder vorhandene droppen).
2. Dump einspielen:

```bash
mysql -u root -p nexis < storage/backups/YYYYMMDD-HHMMSS/database.sql
```

3. Medien zurückkopieren:

```bash
# Windows PowerShell Beispiel
Remove-Item -Recurse -Force storage\media
Copy-Item -Recurse storage\backups\YYYYMMDD-HHMMSS\media storage\media
```

4. `.env` der Staging-Umgebung setzen (`APP_KEY`, DB-Zugangsdaten, `APP_URL`).
5. `composer install --no-dev` (Produktion) bzw. `composer install`.
6. Optional: `php bin/migrate.php` nur wenn das Backup älter ist als neuere Migrationen – sonst nicht nötig.
7. Cache leeren: Ordner `storage/cache/pages` löschen (macht `bin/restore.php` automatisch).
8. Smoke-Test: `/de`, `/admin/login`, Medien-URL, Formular-Submit.

## Hinweise

- Restore reproduziert Inhalte und Medien der gesamten Installation (eine Website).
- Plugin-/Theme-Code kommt aus Git bzw. Deploy-Artefakt, nicht aus dem DB-Backup.
- Nach Restore App-Key unverändert lassen, sonst sind signierte Preview-URLs und ggf. verschlüsselte Settings ungültig.
