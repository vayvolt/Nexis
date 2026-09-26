# ADR 008: Eine Website pro Installation

## Status

Accepted

## Kontext

Die frühere Produktvision sah Multi-Site / Mandantenfähigkeit vor (mehrere Sites pro Installation, Host-Auflösung, Site-Picker im Admin). Für den konkreten Einsatz von Nexis reicht **eine** Website pro Installation.

## Entscheidung

- Eine Installation verwaltet genau **eine** Website.
- Das Datenmodell behält `sites`, `site_id` und optional `tenants` als technische Hülle (1:1), damit Locale-, Medien- und Rechte-Strukturen unverändert bleiben.
- Öffentliche und Admin-Schicht laden die Website über `SiteRepository::installed()`, nicht über Host → Site-Auswahl.
- Admin-URLs sind flach (`/admin/pages`, `/admin/media`), ohne `/admin/sites/{id}/…`.
- Seed und Doku beschreiben nur noch eine Demo-Website.

## Konsequenzen

- Kein Site-Picker, kein Mandanten-Wechsel in der UI.
- Host-Aliase (`site_domains`) bleiben für Canonicals / spätere Domain-Logik nutzbar, steuern aber nicht mehr die Inhalte.
- Multi-Site müsste später bewusst wieder eingeführt werden; das Schema erleichtert das, die Produkt-UI nicht.
