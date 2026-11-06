# Aufbewahrung und automatische Löschung

Was Nexis beim Queue-Lauf (`php bin/queue-work.php`) löscht, und was Betreiber selbst steuern. Kein Rechtsrat.

Siehe auch: [queue.md](queue.md), [go-live.md](go-live.md), [dpa-subprocessors.md](dpa-subprocessors.md).

## Automatischer Purge (Queue-Worker)

| Daten | Default | Env | Minimum |
|---|---|---|---|
| Audit-Log (`audit_log`) | 180 Tage | `AUDIT_RETENTION_DAYS` | 30 |
| Formular-Einreichungen (`plugin_nexis_forms_submissions`) | 365 Tage | `FORMS_RETENTION_DAYS` | 30 |
| Idempotency-Keys | Ablaufzeit des Keys | — | — |
| Backups | Anzahl Stempel (Planer) | UI `/admin/backups` (`backup.schedule.retain`) | siehe Planer |

Ohne regelmäßigen Queue-Worker (oder Cron) laufen diese Purges nicht.

## Bewusst ohne Auto-Purge

| Daten | Hinweis |
|---|---|
| Benutzerkonten | Soft-Delete / Selbstlöschung; kein Alters-Purge inaktiver Konten |
| Mail-Log | Manuell prüfen/löschen unter Admin → Mail |
| Medienbibliothek | Kein EXIF-/Alters-Purge; Betreiber löscht Assets |
| Seiten / Revisionen | Redaktionell; kein automatisches Löschen veröffentlichter Inhalte |
| Cookie-Consent | Clientseitig; kein Server-Log der Wahl |

## Empfehlung vor Go-Live

- [ ] `AUDIT_RETENTION_DAYS` / `FORMS_RETENTION_DAYS` an interne Löschfristen anpassen (`.env`)
- [ ] Backup-Aufbewahrung im Planer setzen und Restore testen
- [ ] In der Datenschutzerklärung die tatsächlichen Fristen nennen
