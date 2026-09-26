# Restore

Ein Backup von `php bin/backup.php` liegt unter `storage/backups/{timestamp}/` und enthält:

- `database.sql` – logischer MariaDB-Dump (`--single-transaction`)
- `media/` – Kopie von `storage/media/`
- `manifest.json` – Metadaten

Unter XAMPP/Windows sucht das Skript `mysqldump` im PATH und unter `…\xampp\mysql\bin\`. Optional: `MYSQLDUMP_PATH` in `.env` setzen.

`.env` und Secrets gehören **nicht** ins Backup-Archiv; getrennt und verschlüsselt sichern.

## Restore auf Staging

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
7. Cache leeren: Ordner `storage/cache/pages` löschen.
8. Smoke-Test: `/de`, `/admin/login`, Medien-URL, Formular-Submit.

## Hinweise

- Restore reproduziert Inhalte und Medien der gesamten Installation (eine Website).
- Plugin-/Theme-Code kommt aus Git bzw. Deploy-Artefakt, nicht aus dem DB-Backup.
- Nach Restore App-Key unverändert lassen, sonst sind signierte Preview-URLs und ggf. verschlüsselte Settings ungültig.
