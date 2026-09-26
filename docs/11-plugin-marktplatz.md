# 11 Plugin-Marktplatz

Öffentlicher Plugin-Katalog (Directory) und Anbindung im Nexis-CMS.

**Stand:** 2026-09-22 · Status: MVP (Browse, Install, Update-Check)

## 11.1 Zwei Systeme

| Teil | Rolle |
|---|---|
| **Directory** | Öffentlicher Katalog, Developer-Einreichung, Review, Downloads, Directory-API |
| **CMS-Client** | Unter `/admin/plugins` suchen, installieren, Updates prüfen |

Produktive Directory-URL (fest im CMS): `https://nexis.vayvolt.de` (`MarketplaceClient::DIRECTORY_URL`).

## 11.2 Scope

| Thema | Entscheidung |
|---|---|
| Geschäftsmodell Phase 1 | **Nur freie Plugins** — kein Bezahl-Marktplatz |
| Lizenz | **GPL-2.0-or-later** empfohlen; sonst GPL-kompatibel + Quellcode |
| Trialware | **Verboten** (Feature-Locks / Paywalls im ausgelieferten Code) |
| Freigabe | **Manuelles Review** vor `approved` |
| Paketformat | ZIP mit `plugin.json` (wie CMS-Upload) |
| Signatur | Optional Ed25519; CMS prüft bei gesetztem `PLUGIN_TRUST_PUBLIC_KEY` |

## 11.3 CMS-Funktionen

| UI / Flow | Zweck |
|---|---|
| `/admin/plugins/marketplace` | Katalog durchsuchen und installieren |
| `/admin/plugins` | Update-Hinweis, wenn Directory eine neuere Version meldet |
| Install | Download → SHA-256 → `PluginPackageInstaller` |
| Update-Check | `POST /api/v1/update-check` (Core + Plugins), anonyme Install-Infos |

## 11.4 Vorbild (Directory-Patterns)

Übernehmen: zentrale Listing-Stelle, manuelles Review, Lizenz-/Anti-Trialware-Regeln, Readme-Standard.  
Nicht übernehmen (vorerst): Bezahlplugins, Nutzungslimits, ungefragte Frontend-Attribution.

## 11.5 API-Vertrag (MVP)

Basis-URL: Directory-Root (Produktion: `https://nexis.vayvolt.de`).

### `GET /api/v1/plugins`

Query: `q`, `compatible`, `page`, `perPage` — Antwort mit `items`, `total`, `page`, `perPage`.

### `GET /api/v1/plugins/{vendor}/{name}`

Detail inkl. `latest` (version, compatibleCore, packageSha256, downloadUrl).

### `POST /api/v1/update-check`

JSON-Body: `install_id` (UUID), `nexis`, `php`, `db`, `locale`, optional `plugins` (`slug => version`).  
Antwort: `cms` (latest, downloadUrl, updateAvailable) und `plugins` (Updates).  
Nebeneffekt: Upsert eines anonymen Installations-Datensatzes; öffentliche Statistik unter `/about/stats` auf dem Directory.

### Weitere Directory-Oberflächen

| Pfad | Zweck |
|---|---|
| `/` | Marketing / Einstieg |
| `/plugins` | Öffentlicher Katalog |
| `/developers` | Developer-Portal (Einreichung) |
| `/review` | Review-Queue |
| `/admin` | Directory-Administration |
| `/wiki` | Entwickler-Wiki (Hooks, Slots, Events, Manifest) |

## 11.6 Sicherheit und Betrieb (Directory)

- Rate-Limits und Download-Logs
- ZIP-Validator (Pfade, Größe, Dateitypen, Lizenz, Heuristiken)
- Optionale Paket-Signatur (Ed25519)
- Manuelles Review vor Freigabe

Siehe auch [04 Plugin-System](04-plugin-system.md#412-öffentliches-directory--cms-marktplatz) und [06 Sicherheit und Betrieb](06-sicherheit-und-betrieb.md).
