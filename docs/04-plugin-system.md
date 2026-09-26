# 04 Plugin-System

Plugins sind der einzige vorgesehene Weg, Fachfunktionen hinzuzufügen. Der Kern bleibt bewusst klein. Ein Plugin darf Routen, Blöcke, Settings, Admin-Slots, Jobs, Permissions und Migrationen beitragen – nie Core-Dateien überschreiben.

## 4.1 Grundsätze

1. **Vertrag vor Implementierung.** Alles, was ein Plugin tun darf, steht in Interfaces und im Manifest.
2. **Site-Scope.** Aktivierung und Konfiguration gelten pro Site, der Code liegt einmal im Dateisystem.
3. **SemVer.** `compatibleCore` im Manifest wird vom Loader erzwungen.
4. **Fail-soft.** Render- und Boot-Fehler eines Plugins legen nur dieses Plugin lahm.
5. **Keine stillen Hooks in fremde Daten.** Schreibzugriffe auf fremde Aggregate nur über publizierte Application-Services.

## 4.2 Manifest `plugin.json`

```json
{
  "id": "acme/forms",
  "name": "Formulare",
  "version": "1.2.0",
  "author": "Acme GmbH",
  "compatibleCore": "^1.0",
  "php": ">=8.4",
  "type": "plugin",
  "autoload": "Acme\\Forms\\",
  "provider": "Acme\\Forms\\FormsServiceProvider",
  "provides": {
    "blocks": ["acme/forms/contact", "acme/forms/newsletter"],
    "permissions": ["forms.export", "forms.manage"],
    "slots": ["admin.sidebar", "theme.footer"]
  },
  "requires": {
    "plugins": []
  },
  "migrations": "migrations"
}
```

Optional: `author` als String oder Objekt `{ "name": "…", "url": "…", "email": "…" }` (angezeigt wird der Name).

Ungültige Manifeste werden nicht geladen. Unbekannte Felder sind erlaubt, werden aber ignoriert (Forward-Compatibility).

`requires.plugins` wird erzwungen: fehlende Abhängigkeiten verhindern Aktivierung im Admin und skippen den Boot (Log + Fail-soft). Abhängige Plugins booten erst nach ihren Requirements (topologische Pass-Reihenfolge).

Plugin-Settings liegen in `site_settings` unter `plugin:{id}.{field}`. Schema via `$kernel->registerSettings([...])`; Zugriff über `$kernel->pluginSettings()`. Feldtyp `secret` erzwingt Verschlüsselung (`SecretBox`). Bei Deinstallation löscht der Kern automatisch `plugin:{id}.*`.

## 4.3 Service Provider

Jedes Plugin stellt genau einen Provider:

```php
interface PluginServiceProvider
{
    public function register(Container $app): void;

    public function boot(Kernel $kernel): void;
}
```

- `register`: Bindings in den Container, keine Side-Effects.
- `boot`: Events, Routen, Blöcke, Slots – erst nachdem alle Plugins registriert sind.

Provider dürfen den Kernel nicht ersetzen und keine anderen Provider instantiieren.

## 4.4 Extension Points

| Punkt | Zweck | Beispiel |
|---|---|---|
| `BlockType` | Editor- und Frontend-Renderer | FAQ-Akkordeon |
| `RouteCollector` | Public- und Admin-Routen | `/forms/{id}/submit` |
| `AdminSlot` | UI-Einschübe | Sidebar-Link, Dashboard-Kachel |
| `registerAdminNavSection` | Aktiver Admin-Menüpunkt (Pfad / Page-Typ) | Blog markiert `/admin/blog` und `post` |
| `registerMailJobHandler` | Mail-Job-Typen in Queue `mail` | Shop-Bestellmail |
| `registerJobHandler` | Eigene Queue + Worker (`bin/queue-work.php`) | Bestell-Export, Payment-Capture |
| `registerPageCacheBypass` | Full-Page-Cache überspringen | Warenkorb-Session / Checkout-Pfad |
| `registerWebhookEvent` + `WebhookDispatcher::dispatch` | Custom Webhook-Events | `shop.order.placed` |
| `registerSitemapPath(s)` | Extra Sitemap-URLs | `/blog`, `/shop` |
| `registerPrimaryNavLink` | Primary-Nav-Eintrag (alle Locales) | Blog-/Katalog-Archiv |
| `registerTwigExtension` | Twig-Funktionen in Themes | `cart_count()` |
| `listen` | Domain-Events (Wrapper um `EventDispatcher`) | `UserAuthenticated`, `SiteResolved` |
| `registerSettings` / `pluginSettings()` | Typisierte `site_settings` (`plugin:{id}.{field}`) | Mail-Empfänger, API-Tokens |
| `registerPolicy` + `SitePolicy::allows` | Objekt-Autorisierung | Bestellung anzeigen |
| `registerCspContributor` | CSP-Quellen ergänzen | Consent/GA |
| `registerCsrfExempt` | CSRF für `/ext/…`-Prefix überspringen | Payment-Webhook |
| `Idempotency` | Shared Idempotency-Key-Protokoll | Form-/Checkout-POST |
| `HmacSignature` | HMAC prüfen/signieren (`sha256=…`) | Inbound Provider-Callbacks |
| `SessionStore::flash` / `pullFlash` | One-shot Session-Werte (PRG) | „Bestellung bestätigt“ |
| `Money` / `PaymentPort` | Geldbeträge + optionales Payment-Gateway | Checkout-Plugin |
| `PermissionProvider` / `registerPermissions` | Rechte aus Plugin-Manifest oder Hook | `forms.manage` |
| `AdminSlot` `admin.dashboard.content` / `.site` | Dashboard-Schnelllinks | Forms, Blog, Consent |
| `AdminSlot` `theme.header-actions` | Header-UI neben Nav | Warenkorb-Badge |

## 4.5 Permissions

Core-Rechte stehen nur in `Nexis\Auth\Permission` (`core()` / `editorDefaults()` / `seoDefaults()`). System-Rollen werden über `SystemRoleSeeder` / `SystemRoleTemplates` angelegt (Website-Admin, Redakteur, SEO, Mitglied). Plugin-Rechte kommen aus:

1. Manifest `provides.permissions` (beim Boot automatisch registriert; Ziel-Rollen über `provides.permissionRoles`, Default `admin` + `editor`), oder
2. Hook `$kernel->registerPermissions(['acme/thing.manage'], ['admin', 'editor'])` im Provider.

Der Kernel schreibt die Grants am Ende des Plugin-Boots via `syncPermissions()` in die DB (`INSERT IGNORE`). Bei Deinstallation widerruft der Kern die Manifest-Permissions für die Rollen der betroffenen Site (`revokeForSite`). **Deaktivieren** belässt die Grants (erneutes Aktivieren braucht keinen erneuten Grant dank `INSERT IGNORE`).

## 4.6 Events (Kern, nicht abschließend)

| Event | Wann |
|---|---|
| `PageRendering` / `BlockRendering` | vor Dokument- bzw. Block-Render (Props/Context anreicherbar) |
| `PagePublished` | nach Snapshot-Commit |
| `PageUnpublished` | nach Zurücknehmen der Veröffentlichung |
| `MediaUploaded` | nach erfolgreichem Upload (`MediaLibrary::store`) |
| `PluginToggled` | Aktivieren / Deaktivieren (`plugin.enabled` / `plugin.disabled`) |
| `UserAuthenticated` | erfolgreicher Login (inkl. 2FA) |
| `UserRegistered` | nach öffentlicher Registrierung |
| `SiteResolved` | installierte Site auf dem Request (Middleware) |
| `PublicNotFound` | öffentliche 404 |

Listener: `$kernel->listen(EventClass::class, $callable)` oder `EventDispatcher` direkt. Langlaufende Arbeit gehört auf die Queue.

## 4.7 Block-Vertrag

```php
interface BlockType
{
    public function type(): string;              // vendor/name
    public function label(): string;
    public function allowsChildren(): bool;
    public function propsSchema(): object|array; // JSON Schema
    public function defaultProps(): array;
    public function render(array $block, array $context): string;
}
```

Admin-UI für den Block kommt aus dem Schema (Auto-Form) oder aus einer optionalen `EditorView`. Ein Plugin darf fremde Blocktypen nicht überschreiben. Verzierung fremder Blöcke läuft über `BlockRendering`.

Öffentliches Entwickler-Wiki (Directory): `https://nexis.vayvolt.de/wiki` — Hooks, Slots, Events, Einreichung.

## 4.8 UI-Strings, Views und Assets

Plugins mit sichtbarem Text liefern `resources/lang/{locale}.json`. Keys liegen im Plugin-Namensraum (z. B. `admin.nav.*`, `admin.plugins.desc.*`). Der Kernel lädt Lang-Pfade für alle *discovered* Plugins (auch deaktivierte), damit Admin-Texte wie Beschreibungen auf der Plugin-Seite auflösbar bleiben.

Admin-Views und öffentliche Assets liegen ebenfalls unter dem Plugin:

| Pfad | Zweck |
|---|---|
| `resources/lang/{locale}.json` | UI-Strings |
| `resources/views/…` | PHP-Views (Admin/Account), per `ViewRenderer::addPath` |
| `resources/assets/…` | CSS/JS → Publish nach `assets/plugins/{vendor}/{name}/` |

Der `RenderContext` stellt `locale()` und `translate()` bereit. Plugins dürfen Locale nicht aus `Accept-Language` selbst ableiten, wenn der Kern sie schon aus der URL gesetzt hat.

Kurze mehrsprachige Felder in Plugin-Tabellen: `Nexis\I18n\LocalizedMap` (JSON im Datensatz) + Locale-Tabs wie beim Consent-Plugin. Lange Inhalte bleiben Pages mit `translation_group_id`.

Zusätzliche Account-Navigation: Slot `account.nav` bzw. `PluginKernel::registerAccountNavLink`.

Aktiver Admin-Menüpunkt: Plugins melden Besitz über `$kernel->registerAdminNavSection('blog', ['paths' => ['/admin/blog'], 'pageTypes' => ['post']])`. Der Kern kennt nur Core-Routen (`pages`, `menus`, …) und fragt Plugins zuerst.

## 4.9 Lebenszyklus

```text
discovered → installed → enabled → disabled → uninstalled
```

| Status | Bedeutung |
|---|---|
| discovered | Manifest lesbar, noch keiner Site zugeordnet |
| installed | Migrationen gelaufen, Settings-Schema bekannt |
| enabled | Provider bootet, Blöcke und Routen aktiv |
| disabled | Code bleibt, Runtime lädt das Plugin nicht |
| uninstalled | Site-Daten, Katalogeintrag, veröffentlichte Assets und Plugin-Code entfernt |

Deaktivieren löscht keine Inhaltsdaten. Deinstallieren entfernt die Site-Installation, ruft optional einen `UninstallHandler` (Manifest-Feld `uninstall`) auf, leert `storage/plugin/{vendor}/{name}/`, entfernt den Katalogeintrag sowie `assets/plugins/{vendor}/{name}/` und löscht das Paket unter `plugins/{vendor}/{name}/`.

## 4.10 Isolation und Rechte des Dateisystems

Plugins dürfen schreiben nach:

- `storage/plugin/{vendor}/{name}/{site_id}/`
- eigene Tabellen über Migrationen

Plugins dürfen **nicht**:

- `src/` des Kerns verändern
- andere Plugin-Verzeichnisse schreiben
- `themes/` überschreiben (nur Slots und Assets registrieren)
- PHP-Dateien zur Laufzeit evaluieren (`eval`, dynamisches `include` fremder Uploads)

Der Autoloader lädt nur PSR-4 aus dem im Manifest deklarierten Namespace.

## 4.11 Sicherheit im Plugin-Kanal

- Admin-Installation nur mit Permission `plugin.install`
- Optional: Signatur des Plugin-Zips mit Ed25519 (`PLUGIN_TRUST_PUBLIC_KEY`); Prüfung im Staging vor dem Verschieben nach `plugins/`
- Upload nur als Archiv (`POST /admin/plugins/upload`), Entpacken in `storage/tmp/`, danach atomare Verschiebung
- Zip-Slip-Pfade und ungültige Plugin-IDs werden abgelehnt
- `plugin.json` darf keine beliebigen PHP-Bootstrap-Pfade außerhalb `src/` setzen
- Settings-Formulare laufen durch denselben CSRF- und Policy-Stack wie der Kern

Nach dem ZIP-Install ist das Plugin **installiert**, aber nicht automatisch **aktiviert**.

## 4.12 Öffentliches Directory / CMS-Marktplatz

Freie Plugins werden über die separate Directory-Website veröffentlicht. Im CMS:

| Einstellung / UI | Zweck |
|---|---|
| Directory-URL (fest) | `https://nexis.vayvolt.de` (`MarketplaceClient::DIRECTORY_URL`) |
| `/admin/plugins/marketplace` | Suchen, installieren, Updates |
| `/admin/plugins` | Update-Hinweis, wenn Directory eine neuere Version hat |

Install: Download → SHA-256 → `PluginPackageInstaller` (optional Ed25519 via `PLUGIN_TRUST_PUBLIC_KEY`).  
API und Ops: [11 Plugin-Marktplatz](11-plugin-marktplatz.md). Entwickler-Wiki: `https://nexis.vayvolt.de/wiki`.

## 4.13 First-Party vs. Third-Party

First-Party-Plugins liegen unter `plugins/nexis/` und folgen denselben Verträgen. Es gibt keine versteckten Core-Hintertüren. Das hält den Kern testbar und verhindert, dass interne Module die API umgehen.

Im **öffentlichen Git-Repo** (Soft Launch) sind u. a. geliefert:

| Plugin | Rolle |
|---|---|
| `nexis/forms` | Kontaktformular, Submissions, Mail-Notify |
| `nexis/redirects` | Redirects + 404-Protokoll |
| `nexis/consent` | Consent-Banner, Google Consent Mode |
| `nexis/blog` | Beiträge (`type=post`), Archiv, Listen-Block |
| `nexis/catalog` | Produkte (`type=product`), Archiv (ohne Warenkorb) |

Shop / Checkout ist **nicht** im Soft-Launch-Kern. First-Party `nexis/shop` (geplant) oder Third-Party können darauf aufbauen. Dafür liefert der Kern u. a. Mail-/Job-Registries, Page-Cache-Bypass, `requires.plugins`, `Money`/`PaymentPort`, Webhook-Dispatch und Session-/Idempotency-Stores. Was für einen Shop-Plugin **weiterhin fehlt bzw. plugin-seitig zu bauen** ist:

| Thema | Status |
|---|---|
| Warenkorb / Checkout / Bestellungen | Plugin-Tabellen + Routen |
| Zahlungsgateway-Implementierung | Plugin bindet `PaymentPort` (Core nur Interface) |
| Steuer / Versand / Gutscheine | Plugin-Fachlogik |
| Lagerbestand / Reservierung | Plugin (optional Catalog erweitern) |
| Cart-aware Page-Cache | Plugin: `registerPageCacheBypass` |
| Bestell-Mails / Async Jobs | Plugin: `registerMailJobHandler` / `registerJobHandler` |
| Webhooks `shop.*` | Plugin: `registerWebhookEvent` + `WebhookDispatcher::dispatch` |
| Sitemap / Primary-Nav | Plugin: `registerSitemapPath` / `registerPrimaryNavLink` |
| Theme-Helfer | Plugin: `registerTwigExtension` |
| Settings (Keys / Secrets) | Plugin: `registerSettings` + `pluginSettings()`; Uninstall wischt `plugin:{id}.*` |
| Generische Settings-Admin-UI | `/admin/plugins/settings` für `registerSettings`-Schemas |
| Objekt-Policies | Plugin: `registerPolicy` → `SitePolicy::allows` |
| Site-Boot-Hook | Event `SiteResolved` (Request-Attribut `site`) |
| CSRF-Exempt für Provider-Callbacks | Plugin: `registerCsrfExempt('/ext/…')` + `HmacSignature::verify` |
| Idempotency-Protokoll | Core: `Idempotency` (Forms nutzt es bereits) |
| Header-UI (Warenkorb) | Theme-Slot `theme.header-actions` |
| Flash nach PRG | `SessionStore::flash` / `pullFlash` |

## 4.14 Versionierung der Plugin-API

Die Plugin-API hat eine eigene Major-Version, entkoppelt vom Marketing-Release. Änderungen:

- **Patch:** Bugfixes, neue optionale Manifest-Felder
- **Minor:** neue Events, neue optionale Interface-Methoden mit Default in Traits/Abstracts
- **Major:** entfernte Events, geänderte Methodensignaturen, geänderte Block-JSON-Pflichten

`compatibleCore` nutzt Composer-SemVer-Ranges.

## 4.15 Testpflicht für Plugins

Jedes First-Party-Plugin liefert:

- Schema-Tests der Blöcke
- Boot-Test (Provider registriert ohne Exception)
- mind. einen Render-Test pro Block
- Migration vorwärts/rückwärts

Third-Party-Plugins sollen denselben Harness nutzen (`nexis/plugin-test`, geplant).
