# 11 Plugin- und Theme-Marktplatz

Öffentlicher Katalog (Directory) und Anbindung im Nexis-CMS für **Plugins** und **Themes**.

**Stand:** 2026-09-28 · Status: MVP (Browse, Install, Update-Check Plugins/Themes; Downloads-Stats; Sterne-Bewertungen)

## 11.1 Zwei Systeme

| Teil | Rolle |
|---|---|
| **Directory** | Öffentlicher Katalog, Developer-Einreichung (Plugins), Review, Downloads, Directory-API |
| **CMS-Client** | Unter `/admin/plugins` und `/admin/theme` suchen, installieren, Updates prüfen |

Produktive Directory-URL (fest im CMS): `https://nexis.vayvolt.de` (`MarketplaceClient::DIRECTORY_URL`).

## 11.2 Scope

| Thema | Entscheidung |
|---|---|
| Geschäftsmodell Phase 1 | **Nur freie** Plugins/Themes — kein Bezahl-Marktplatz |
| Lizenz | **GPL-2.0-or-later** empfohlen; sonst GPL-kompatibel + Quellcode |
| Trialware | **Verboten** (Feature-Locks / Paywalls im ausgelieferten Code) |
| Freigabe | **Manuelles Review** vor `approved` (Plugins und Themes) |
| Paketformat Plugins | ZIP mit `plugin.json` (wie CMS-Upload) |
| Paketformat Themes | ZIP mit `theme.json` (Twig/Tokens/Assets; **kein PHP**) |
| Signatur | Optional Ed25519; CMS prüft bei Plugins mit gesetztem `PLUGIN_TRUST_PUBLIC_KEY` |

## 11.3 CMS-Funktionen

| UI / Flow | Zweck |
|---|---|
| `/admin/plugins/marketplace` | Plugin-Katalog durchsuchen und installieren |
| `/admin/plugins` | Update-Hinweis, wenn Directory eine neuere Version meldet |
| `/admin/theme/marketplace` | Theme-Katalog durchsuchen und installieren |
| `/admin/theme` | Lokale Themes aktivieren; Link zum Katalog |
| Install Plugin | Download → SHA-256 → `PluginPackageInstaller` |
| Install Theme | Download → SHA-256 → `ThemePackageInstaller` |
| Update-Check | `POST /api/v1/update-check` (Core + Plugins + Themes), anonyme Install-Infos |
| Seed (Directory) | `storage/seed/plugins.json` + `themes.json`; `php bin/pack-seed-{plugins,themes}.php` |

## 11.4 Vorbild (Directory-Patterns)

Übernehmen: zentrale Listing-Stelle, manuelles Review, Lizenz-/Anti-Trialware-Regeln, Readme-Standard.  
Nicht übernehmen (vorerst): Bezahlplugins, Nutzungslimits, ungefragte Frontend-Attribution.

## 11.5 API-Vertrag (MVP)

Basis-URL: Directory-Root (Produktion: `https://nexis.vayvolt.de`).

### `GET /api/v1/plugins`

Query: `q`, `compatible`, `page`, `perPage` — Antwort mit `items`, `total`, `page`, `perPage` (inkl. `downloads`, `ratingAverage`, `ratingCount`).

### `GET /api/v1/plugins/{vendor}/{name}`

Detail inkl. `latest` (version, compatibleCore, packageSha256, downloadUrl, downloads) sowie `ratingAverage` / `ratingCount`.

### `GET /api/v1/themes`

Analog Plugins — Query und Pagination wie oben (inkl. Ratings).

### `GET /api/v1/themes/{vendor}/{name}`

Detail inkl. `latest`, Ratings und Download-URL unter `/api/v1/themes/.../download/{version}`.

### `POST /api/v1/update-check`

JSON-Body: `install_id` (UUID), `nexis`, `php`, `db`, `locale`, optional `plugins` und `themes` (`slug => version`).  
Antwort: `cms` (latest, downloadUrl, updateAvailable), `plugins` und `themes` (Updates).  
Nebeneffekt: Upsert eines anonymen Installations-Datensatzes; öffentliche Statistik unter `/about/stats` auf dem Directory.

### Weitere Directory-Oberflächen

| Pfad | Zweck |
|---|---|
| `/` | Marketing / Einstieg |
| `/plugins` | Öffentlicher Plugin-Katalog (Downloads + Sterne) |
| `/themes` | Öffentlicher Theme-Katalog (Downloads + Sterne) |
| `/developers` | Developer-Portal (Plugin- und Theme-Einreichung; Bewertung nach Login) |
| `/review` | Review-Queue (Plugins + Themes) |
| `/admin` | Directory-Administration (u. a. `/admin/ratings`) |
| `/wiki` | Entwickler-Wiki (Hooks, Slots, Events, Manifest) |

## 11.6 Sicherheit und Betrieb (Directory)

- Rate-Limits und Download-Logs
- ZIP-Validator (Pfade, Größe, Dateitypen, Lizenz, Heuristiken) für Plugin-Einreichungen
- Optionale Paket-Signatur (Ed25519)
- Manuelles Review vor Freigabe

Siehe auch [04 Plugin-System](04-plugin-system.md#412-öffentliches-directory--cms-marktplatz), [05 Individualisierung und Theming](05-individualisierung-und-theming.md) und [06 Sicherheit und Betrieb](06-sicherheit-und-betrieb.md).
