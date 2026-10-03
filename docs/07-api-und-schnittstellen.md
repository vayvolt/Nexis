# 07 API und Schnittstellen

> **Status:** REST-API und API-Tokens sind vollständig: Lesen (Public + Admin), Token-Verwaltung unter `/admin/api-tokens` und **Schreiben** (Website, Seiten, Übersetzungen, Builder-Dokument mit `If-Match`, Publish/Unpublish/Revert, Medien-Upload, Plugin-Status, Theme-Tokens, Block-Registry). Offen ist nur CORS für Browser-Clients (optional). Nicht implementierte Zeilen sind unten mit „geplant" markiert.

### Im MVP enthalten

| Bereich | Stand |
|---|---|
| Fehler-Envelope inkl. `requestId` | implementiert |
| Erfolgs-Envelope `{ data, meta?, requestId }` | implementiert |
| `GET /api/v1/public/sites/current` | implementiert |
| `GET /api/v1/public/pages?locale=&type=` | implementiert |
| `GET /api/v1/public/pages/by-path?path=&locale=` | implementiert |
| `GET /api/v1/public/pages/{id}` | implementiert |
| `GET /api/v1/admin/site` | implementiert |
| `GET /api/v1/admin/pages?locale=&type=` | implementiert |
| `GET /api/v1/admin/pages/{id}` | implementiert |
| `GET /api/v1/admin/pages/{id}/document` | implementiert |
| `GET /api/v1/admin/media` | implementiert |
| `GET /api/v1/admin/tokens`, `DELETE /api/v1/admin/tokens/{id}` | implementiert |
| Bearer-Tokens (`nx_…`), Scopes, Widerruf, `last_used_at` | implementiert |
| `PATCH /api/v1/admin/site` | implementiert |
| `POST /api/v1/admin/pages`, `PATCH /api/v1/admin/pages/{id}` | implementiert |
| `PUT /api/v1/admin/pages/{id}/document` inkl. Optimistic Concurrency (`If-Match`) | implementiert |
| `POST /api/v1/admin/pages/{id}/publish`, `.../unpublish` | implementiert |
| `POST /api/v1/admin/media` (multipart Upload) | implementiert |
| `POST /api/v1/admin/pages/{id}/translations` (idempotent), `GET .../alternates` | implementiert |
| `POST /api/v1/admin/pages/{id}/revert` | implementiert |
| `GET /api/v1/admin/plugins`, `POST /api/v1/admin/plugins/{vendor}/{name}/enable` bzw. `/disable` | implementiert |
| `GET` + `PATCH /api/v1/admin/theme/tokens` | implementiert |
| `GET /api/v1/admin/blocks` | implementiert |
| CORS für Browser-Clients | geplant (optional) |

## 7.1 Prinzipien

- JSON über HTTPS, Version im Pfad: `/api/v1`
- Admin-API ist session- oder token-authentifiziert.
- Public-API ist bewusst klein (Lesen veröffentlichter Inhalte, Form-Submit über Plugin-Routen).
- Fehlerformat einheitlich. Keine HTML-Fehler im API-Modus.
- IDs sind UUID v7. Keine internen Auto-Increments nach außen.
- Erfolgsantworten liefern `{ "data": …, "meta"?: …, "requestId": "…" }`; jede Antwort trägt zusätzlich den Header `X-Request-Id`.

## 7.2 Fehlerformat

```json
{
  "error": {
    "code": "page.not_found",
    "message": "Seite nicht gefunden.",
    "details": {}
  },
  "requestId": "0193f0a0-7c2a-7e11-9c00-5f3c1a9b0abc"
}
```

HTTP-Status bleibt semantisch (`400`, `401`, `403`, `404`, `409`, `422`, `428`, `429`, `500`). `code` ist stabil für Clients, `message` darf übersetzt werden. Bekannte Codes: `auth.unauthorized`, `auth.forbidden` (mit `details.requiredPermissions`), `page.not_found`, `revision.not_found`, `token.not_found`, `plugin.not_found`, `request.invalid`, `site.unavailable`, `http.invalid_json`, `page.path_taken`, `page.if_match_required`, `page.conflict`.

## 7.3 Admin-API (Kern)

Basis: `/api/v1/admin`

| Methode | Pfad | Zweck | Recht | Stand |
|---|---|---|---|---|
| GET | `/site` | Website lesen | CMS-Zugang | implementiert |
| PATCH | `/site` | Website ändern (`name`, `primaryDomain`, `defaultLocale`, `localeUrlStrategy`) | `settings.manage` | implementiert |
| GET | `/pages` | Seitenliste (`?locale=de&type=page`) | `content.page.edit` oder `content.page.seo` | implementiert |
| POST | `/pages` | Seite anlegen (`locale`, `title`, `path`/`slug`, `type`) | `content.page.edit` | implementiert |
| POST | `/pages/{id}/translations` | Fassung in Ziel-Locale anlegen (`locale`, optional `copyBlocks`) | `content.page.edit` | implementiert |
| GET | `/pages/{id}` | Metadaten + `alternates` | `content.page.edit` oder `content.page.seo` | implementiert |
| GET | `/pages/{id}/alternates` | Locales der Übersetzungsgruppe | `content.page.edit` oder `content.page.seo` | implementiert |
| PATCH | `/pages/{id}` | Metadaten ändern | `content.page.edit`; nur SEO-Felder auch `content.page.seo` | implementiert |
| GET | `/pages/{id}/document` | Editor-Dokument der letzten Revision | `content.page.edit` | implementiert |
| PUT | `/pages/{id}/document` | Builder speichern (neue Revision) | `content.page.edit` | implementiert |
| POST | `/pages/{id}/publish` | Snapshot erzeugen | `content.page.publish` | implementiert |
| POST | `/pages/{id}/unpublish` | Snapshot zurücknehmen | `content.page.publish` | implementiert |
| POST | `/pages/{id}/revert` | Revision reaktivieren (`revisionId`) | `content.page.publish` | implementiert |
| GET | `/media` | Medienliste | `content.media.manage` | implementiert |
| POST | `/media` | Upload (`multipart/form-data`: `file`, optional `alt_text`, optional `folder_id`) | `content.media.manage` | implementiert |
| GET | `/tokens` | API-Tokens der Website | `settings.manage` | implementiert |
| DELETE | `/tokens/{id}` | Token widerrufen | `settings.manage` | implementiert |
| GET | `/plugins` | Plugin-Katalog inkl. Status | `plugin.manage` | implementiert |
| POST | `/plugins/{vendor}/{name}/enable` | Plugin aktivieren | `plugin.manage` | implementiert |
| POST | `/plugins/{vendor}/{name}/disable` | Plugin deaktivieren | `plugin.manage` | implementiert |
| GET | `/theme/tokens` | Token-Overrides + Custom-CSS lesen | `theme.manage` | implementiert |
| PATCH | `/theme/tokens` | Token-Overrides ändern | `theme.manage`; `customCss` zusätzlich `theme.custom_css` | implementiert |
| GET | `/blocks` | Block-Registry | CMS-Zugang | implementiert |

Tokens werden **nur in der Admin-UI** (`/admin/api-tokens`) angelegt, damit ein Token keine weiteren Tokens ausstellen kann.

### Schreibende Requests

- Body ist JSON (`{"title": "…"}`). Ungültiges JSON → `400` `http.invalid_json`.
- Es werden **nur die dokumentierten Felder** akzeptiert: unbekannte Schlüssel ergeben `422` (`details.unknownFields`), falsche Werttypen ebenfalls `422` (`details.invalidFields`). Ein Tippfehler wird damit nie stillschweigend ignoriert.
- `PATCH` ist partiell: nicht gesendete Felder bleiben unverändert. Bei `metaTitle`, `metaDescription` und `focusKeyword` leert `null` (oder `""`) den Wert.
- Workflow (`status`, Zeitplanung) läuft nicht über `PATCH /pages/{id}`, sondern über die Publish-Endpunkte.
- `POST /pages` antwortet `201` mit `Location` auf die neue Seite; Pfadkollisionen ergeben `409` `page.path_taken`.
- `POST /media` nutzt **kein JSON**, sondern `multipart/form-data` wie die Medienverwaltung im Admin (`file` Pflicht, `alt_text` für Bilder Pflicht, optional `folder_id`). Antwort `201` mit `Location` auf `/api/v1/admin/media/{id}` und dem gleichen Medien-Objekt wie `GET /media`.
- `robots` akzeptiert nur `index,follow`, `noindex,follow`, `index,nofollow`, `noindex,nofollow` (sonst `422`).

Optimistic Concurrency: `PUT /pages/{id}/document` erwartet `If-Match` mit dem `documentHash` aus `GET /pages/{id}/document` (ETag-Quoting optional). Fehlt der Header → `428` `page.if_match_required`; ein veralteter Hash ergibt `409` `page.conflict` mit dem aktuellen Hash in `details.documentHash`. `If-Match: *` schreibt die erste Revision einer Seite, die noch keine hat. Die Antwort enthält die neue Revision ohne `document`.

```bash
curl -X PUT https://example.com/api/v1/admin/pages/$ID/document \
  -H "Authorization: Bearer nx_…" \
  -H "If-Match: $HASH" \
  -H "Content-Type: application/json" \
  -d '{"document": {…}, "message": "Per API gespeichert"}'
```

### Übersetzungen und Revert

- `POST /pages/{id}/translations` legt die Fassung der Ziel-Locale in derselben Übersetzungsgruppe an (`{"locale": "en", "copyBlocks": true}`; `copyBlocks` ist optional und standardmäßig `true`). Der Aufruf ist **idempotent**: existiert die Locale schon, antwortet er `200` mit dieser Seite, sonst `201` mit `Location`. Die neue Seite ist ein Entwurf und landet wie im Admin im Primärmenü.
- `GET /pages/{id}/alternates` liefert alle Locales der Gruppe (inklusive der angefragten Seite) als `{locale, path, id}`.
- `POST /pages/{id}/revert` schreibt das Dokument der Revision aus `revisionId` als **neue** Revision; der veröffentlichte Snapshot bleibt bis zum nächsten Publish unverändert. Fehlendes `revisionId` → `422`, unbekannte oder fremde Revision → `404` `revision.not_found`, paralleler Schreibzugriff → `409` `page.conflict`. Antwort ist die neue Revision inklusive `document`.

### Plugins, Theme-Tokens und Blöcke

- `GET /plugins` synchronisiert den Katalog wie `/admin/plugins` und liefert je Paket `id`/`key` (`vendor/name`), `name`, `version`, `author`, `status` (`discovered`, `installed`, `enabled`, `disabled`), `license`, `compatibleCore`, `php` sowie `available.blocks` / `available.permissions` / `available.slots`.
- `POST /plugins/{vendor}/{name}/enable` bzw. `/disable` schaltet den Status um, schreibt einen Audit-Eintrag und löst `plugin.enabled` / `plugin.disabled` (Webhooks) aus. Unbekannte Pakete ergeben `404` `plugin.not_found`; fehlen benötigte Plugins, antwortet `enable` mit `422` und `details.requires`.
- `PATCH /theme/tokens` ist partiell (`{"tokens": {"color.brand.primary": "#0f172a"}, "customCss": "…"}`): nur gesendete Tokens ändern sich, `null` oder `""` entfernt den Override, ein Wert gleich dem Theme-Default wird nicht gespeichert. Erlaubt sind genau die Tokens des Branding-Formulars; `GET /theme/tokens` liefert sie in `meta.editableTokens` (Token → erlaubte Werte, `null` = Freitext). Unbekannte Keys ergeben `422` mit `details.unknownTokens`, ungültige Werte der Auswahl-Tokens `422` mit `details.token`. `customCss` verlangt zusätzlich `theme.custom_css` und wird wie im Admin sanitisiert.
- `GET /blocks` liefert die Block-Registry inklusive Plugin-Blöcken (`type`, `label`, `allowsChildren`, `propsSchema`, `defaultProps`) — die Vorlage für Dokumente in `PUT /pages/{id}/document`.

### Authentifizierung

- Session-Cookie (Admin angemeldet) **oder** `Authorization: Bearer nx_<secret>`.
- Token-Requests sind CSRF-frei (kein Cookie, keine Session); session-basierte Schreibzugriffe brauchen weiter `_csrf` bzw. den Header `X-CSRF-Token` (bei JSON-Bodys nur der Header).
- Ohne gültige Authentifizierung antwortet die Admin-API mit JSON `401` (`WWW-Authenticate: Bearer`) statt mit einem HTML-Redirect; fehlender CMS-Zugang ergibt `403`.
- Scopes: Ein Token mit leerer Scope-Liste darf alles, was der Benutzer **zum Zeitpunkt der Nutzung** darf. Mit Scopes muss das Recht zusätzlich in der Liste stehen (`SitePolicy` bleibt immer die Obergrenze).
- Gespeichert wird nur `sha256(secret)` in `api_tokens` (plus Anzeige-Präfix, `last_used_at`, `expires_at`, `revoked_at`).

```bash
curl -H "Authorization: Bearer nx_…" https://example.com/api/v1/admin/pages?locale=de
```

## 7.4 Public-API

Basis: `/api/v1/public` (ohne Authentifizierung)

| Methode | Pfad | Zweck | Stand |
|---|---|---|---|
| GET | `/sites/current` | Site zur Host-Header-Domain inkl. Locales | implementiert |
| GET | `/pages?locale=&type=` | Veröffentlichte Seiten der Locale | implementiert |
| GET | `/pages/by-path?path=&locale=` | Snapshot-Dokument, Metadaten, `alternates` | implementiert |
| GET | `/pages/{id}` | wie oben, per UUID | implementiert |
| GET | `/sitemap.xml` | Sitemap-Index; Locale-Sitemaps unter `/sitemap-{locale}.xml` inkl. hreflang | implementiert |

Mehrsegmentige Seitenpfade laufen über `by-path` (Query-Parameter), damit das Routing stabil bleibt.

Kein Zugriff auf Drafts: Nur Seiten mit Status `published` **und** vorhandenem Snapshot werden ausgeliefert, sonst `404`. Preview läuft über signierte Admin-Preview-URLs mit kurzer TTL, nicht über die Public-API.

## 7.5 Plugin-Routen

Plugins hängen eigene Pfade unter:

- `/api/v1/admin/ext/{vendor}/{name}/...`
- `/api/v1/public/ext/{vendor}/{name}/...`
- HTML-Actions: `/ext/{vendor}/{name}/...` (z. B. Form-POST)

Damit bleiben Kern-Routen stabil und Plugin-Namensräume kollisionsarm.

## 7.6 Interne Ports (PHP)

Plugins und Kern reden nicht über HTTP intern, sondern über Ports:

```php
interface PagePublisher
{
    public function publish(PageId $id, UserId $actor): Snapshot;
}

interface MediaLibrary
{
    public function store(SiteId $site, UploadedFile $file, UserId $actor): MediaAsset;
}

interface SettingsBag
{
    public function get(string $namespacedKey, mixed $default = null): mixed;
}
```

Neue fachliche Fähigkeiten bekommen zuerst ein Interface im Kern oder im Plugin, dann eine Implementierung. Keine direkten SQL-Zugriffe aus Twig oder Controllern.

## 7.7 Webhooks (Phase 2)

`PagePublished`, `PageUnpublished`, `PageSubmittedForReview`/`PageReviewRejected` und `PluginEnabled`/`PluginDisabled` können ausgehende Webhooks auslösen (`page.published`, `page.unpublished`, `page.review_submitted`, `page.review_rejected`, `plugin.enabled`, `plugin.disabled`). Payload signiert mit HMAC-SHA256 im Header `X-Nexis-Signature: sha256=<hex>`. Retry mit Exponential Backoff über die Queue.

## 7.8 Idempotenz

Schreibende Plugin-Endpunkte (Form-Submit, Zahlungs-Callbacks) akzeptieren `Idempotency-Key` (Header oder Formularfeld `_idempotency_key`). Helper: `Nexis\Http\Idempotency` (`keyFrom` / `check` / `remember`). Der Kern speichert Key + Hash der Anfrage und Antwortmetadaten für 24 h pro Site (`idempotency_keys`). Gleiche Key+Payload → Replay; gleicher Key mit anderer Payload → `409`. Ablaufbereinigung über `php bin/queue-work.php`.

Provider-Callbacks ohne Browser-Session: `$kernel->registerCsrfExempt('/ext/vendor/plugin/webhook')` und Authentizität per `Nexis\Security\HmacSignature::verify` (Header-Format wie Outbound `X-Nexis-Signature: sha256=…`).

Das Kontaktformular (`nexis/forms`) setzt pro Seitenaufruf automatisch einen Key.
