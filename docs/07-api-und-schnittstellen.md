# 07 API und Schnittstellen

> **Status 0.2:** Die HTTP-REST-API und API-Tokens sind **noch nicht implementiert**. Betrieb und Integrationen laufen über die HTML-Admin-UI, Plugin-HTML-Routen (`/ext/…`), Webhooks und interne PHP-Ports. Die Tabellen unten sind das **Zielbild** (noch nicht freigegeben).

## 7.1 Prinzipien

- JSON über HTTPS, Version im Pfad: `/api/v1` (geplant)
- Admin-API ist session- oder token-authentifiziert.
- Public-API ist bewusst klein (Lesen veröffentlichter Inhalte, Form-Submit über Plugin-Routen).
- Fehlerformat einheitlich. Keine HTML-Fehler im API-Modus.
- IDs sind UUID v7. Keine internen Auto-Increments nach außen.

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

HTTP-Status bleibt semantisch (`404`, `401`, `403`, `409`, `422`, `429`, `500`). `code` ist stabil für Clients, `message` darf übersetzt werden.

## 7.3 Admin-API (Kern)

Basis: `/api/v1/admin`

| Methode | Pfad | Zweck |
|---|---|---|
| GET | `/site` | Website lesen |
| PATCH | `/site` | Website ändern |
| GET | `/pages` | Seitenbaum (`?locale=de`) |
| POST | `/pages` | Seite anlegen |
| POST | `/pages/{id}/translations` | Fassung in Ziel-Locale anlegen |
| GET | `/pages/{id}/alternates` | Geschwister der translation_group |
| GET/PATCH | `/pages/{id}` | Metadaten |
| GET | `/pages/{id}/document` | Editor-Dokument |
| PUT | `/pages/{id}/document` | Builder speichern (neue Revision) |
| POST | `/pages/{id}/publish` | Snapshot erzeugen |
| POST | `/pages/{id}/revert` | Revision reaktivieren |
| GET/POST | `/media` | Medienliste / Upload |
| GET | `/plugins` | Installationsstatus |
| POST | `/plugins/{pluginId}/enable` | aktivieren |
| PATCH | `/theme/tokens` | Token-Overrides |
| GET | `/blocks` | Block-Registry |

Optimistic Concurrency: `PUT /document` erwartet `If-Match` mit dem Hash der letzten bekannten Revision. Konflikt → `409`.

## 7.4 Public-API

Basis: `/api/v1/public`

| Methode | Pfad | Zweck |
|---|---|---|
| GET | `/sites/current` | Site zur Host-Header-Domain inkl. Locales |
| GET | `/pages?locale=` | Veröffentlichte Seiten der Locale |
| GET | `/pages/{path}?locale=` | Snapshot, Metadaten, `alternates` |
| GET | `/sitemap.xml` | Sitemap-Index; Locale-Sitemaps unter `/sitemap-{locale}.xml` inkl. hreflang |

Kein Zugriff auf Drafts. Preview läuft über signierte Admin-Preview-URLs mit kurzer TTL, nicht über die Public-API.

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
