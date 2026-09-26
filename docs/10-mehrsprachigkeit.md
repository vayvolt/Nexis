# 10 Mehrsprachigkeit

Mehrsprachigkeit gehört zum Kern, nicht zu einem Plugin. Eine Site kann eine oder viele Locales haben. Inhalte, URLs, Menüs und SEO sind locale-scharf; Themes und Plugins liefern nur eigene UI-Strings.

PHP-`intl` ist Voraussetzung (ICU). Locale-Codes folgen **BCP 47** in Kurzform, z. B. `de`, `de-CH`, `en`, `fr`.

## 10.1 Zwei Achsen

| Achse | Träger | Beispiele |
|---|---|---|
| **Inhaltssprache** | Site + Page + Menu | Öffentliche URL, Blocktexte, Meta-Tags |
| **Admin-UI** | `users.ui_locale` | Labels im Builder, Fehlermeldungen der Admin-API |

Die Admin-Sprache folgt nicht automatisch der *bearbeiteten* Seite: Ein Redakteur kann die deutsche Oberfläche nutzen und eine englische Page im Builder öffnen. Der Umschalter im Admin-Chrome setzt `users.ui_locale`. Ist diese Locale zugleich eine aktivierte Inhalts-Locale der Site, bevorzugen Listen (Titel/Pfad) und der Admin-Content-Kontext dieselbe Sprache – explizites `?locale=` bleibt Vorrang.

## 10.2 Inhaltsmodell

Jede sprachliche Fassung einer Seite ist eine **eigene Page** mit eigenem Blockdokument, eigenen Revisionen und eigenem Snapshot. Es gibt kein JSON-Feld „translations“ innerhalb einer Page.

Zusammengehörige Fassungen teilen sich eine `translation_group_id` (UUID v7). Innerhalb einer Site gilt: höchstens eine Page pro `(translation_group_id, locale)`.

```text
translation_group 0193f0…
├── Page de  /ueber-uns     published
├── Page en  /about-us      published
└── Page fr  /a-propos      draft
```

Regeln:

- Anlegen einer Übersetzung kopiert optional den Blockbaum der Quell-Locale als Startpunkt, danach divergieren die Revisionen.
- `parent_id` zeigt immer auf die Elternseite **derselben Locale**. Ein DE-Kind hängt nicht unter einem EN-Parent.
- Slug und `path` sind pro Locale frei (`kontakt` vs. `contact`).
- Publish ist pro Locale unabhängig. Eine unveröffentlichte Übersetzung erscheint nicht im Language-Switcher und nicht in `hreflang`.
- Löschen einer Fassung entfernt nur diese Page. Die Gruppe bleibt, solange mindestens eine Fassung existiert.

Medien sind **sprachübergreifend** (eine Datei). Alt-Text liegt in `media_assets.alt_text` (bei Bildern Pflicht) und kann in Block-Props überschrieben werden. Fokuspunkt (`focus_x`/`focus_y`, Prozent) steuert `object-position` bei Bild- und Galerie-Blöcken.

### Kurze Labels in einem Datensatz (`LocalizedMap`)

Für kurze, admin-editierbare Strings (z. B. Consent-Texte) gilt ein zweites Muster: **ein Entity-Datensatz** mit Locale→Text-JSON (`Nexis\I18n\LocalizedMap`) und Locale-Tabs im Admin (`data-locale-tabs`). Seiten, Menüs und Blog/Katalog bleiben beim Page-/translation_group-Modell.

## 10.3 URL-Strategien

Pro Site gilt genau eine Strategie (`sites.locale_url_strategy`).

| Strategie | Verhalten | Wann |
|---|---|---|
| `prefix` (Default) | `example.de/de/kontakt`, `example.de/en/contact` | Eine Domain, mehrere Sprachen |
| `prefix` + Default ohne Prefix | `example.de/kontakt` (de), `example.de/en/contact` | Default-Locale soll „nackt“ bleiben |
| `domain` | `www.example.de/kontakt`, `www.example.com/contact` | Eigene Domain je Locale |
| `none` | keine Locale in der URL | Einsprachige Site |

`site_locales.url_prefix` ist bei `prefix` Pflicht (außer Default ohne Prefix: dann leer).  
`site_domains.locale` ist bei `domain` gesetzt; bei `prefix`/`none` bleibt sie `NULL` (Domain gilt für alle Locales).

Bei Strategie `domain` speichert Admin unter Einstellungen → Locales den Host je Sprache (`site_domains`). Öffentliche Alternate-/Canonical-URLs werden dann absolut (`https://{host}{basePath}{path}`) gebaut.

Erkennung zur Laufzeit:

```text
Host → Site
     → Strategie lesen
     → Locale aus Prefix oder Domain
     → Restpfad gegen pages.path der Locale
```

Kein Locale-Raten über `Accept-Language` auf Inhalts-URLs. Ein optionaler First-Visit-Redirect (`/` → Default oder Browser-Match) ist ein Site-Setting (`i18n.detect_on_root`), Default **aus**, weil er SEO und Caches bricht.

## 10.4 Fehlende Übersetzung

Site-Setting `i18n.missing_policy`:

| Wert | Verhalten |
|---|---|
| `404` (Default) | 404 in der angefragten Locale |
| `fallback` | Inhalt der Default-Locale, `noindex`, sichtbarer Hinweis, `hreflang` nur auf existierende Fassungen |

Stilles Umschreiben auf eine andere Sprache ohne Kennzeichnung ist verboten.

## 10.5 SEO und Markup

Jede veröffentlichte Fassung:

- `<html lang="{bcp47}">`
- Canonical auf die eigene Locale-URL
- `hreflang` für alle **veröffentlichten** Geschwister plus `x-default` auf die Default-Locale
- Sitemap-Index mit einem Sitemap-Dokument pro Locale; Einträge mit `xhtml:link rel="alternate" hreflang="…"`

Open Graph `og:locale` und `og:locale:alternate` folgen derselben Gruppe.

JSON-LD (`application/ld+json`) wird vom Kern in `theme.head` ausgegeben: `WebPage` (+ `FAQPage`, wenn FAQ-Blöcke im Snapshot), plus `WebSite`/`Organization`. Plugins erweitern den Graph über Event `StructuredDataBuilding` (z. B. `nexis/catalog` → `Product`/`Offer`). Bei `noindex` entfällt Structured Data. Shop/Warenkorb bleibt Plugin-Thema.

## 10.6 Language-Switcher

Das Theme rendert den Switcher im Header über `alternates` (Locale-Links). Veröffentlicht: Link. Unveröffentlicht: weglassen (`hide`) oder als „bald verfügbar“ markieren (`label`) – Setting `i18n.switcher_unpublished`. `hreflang` nur für veröffentlichte Geschwister.

Der Switcher setzt keine Session-Locale. Die URL ist die Quelle der Wahrheit. Ein Cookie ist nur für den optionalen Root-Detect erlaubt.

## 10.7 UI-Strings (Kern, Theme, Plugin)

Inhalte der Blöcke sind Redakteurssache. Framework-Strings nicht.

| Quelle | Ort | Key |
|---|---|---|
| Kern | `resources/lang/{locale}.json` | `core.page.not_found` |
| Theme | `themes/{name}/lang/{locale}.json` | `theme.footer.imprint` |
| Plugin | `plugins/{vendor}/{name}/resources/lang/{locale}.json` | `{vendor}/{name}.form.submit` |

Fallback-Kette: angeforderte Locale → Sprache ohne Region (`de-CH` → `de`) → Site-Default → `de` (Kern-Fallback).

Platzhalter als `:name` (einfache Substitution in `Nexis\I18n\Translator`). Datums- und Zahlenformat über `IntlDateFormatter` / `NumberFormatter` in der Inhalts-Locale, wo genutzt.

Themes und Plugins dürfen keine Inhaltsseiten übersetzen. Sie dürfen Locale nur lesen (`RenderContext::locale()`).

## 10.8 Admin und Builder

- Admin-UI-Locale: Umschalter im Chrome (`/admin/ui-locale`). Wenn die gewählte UI-Locale eine aktivierte Site-Inhaltssprache ist, werden Listen-Primärfassung (Titel/Pfad) und Content-Session daran ausgerichtet; `?locale=` überschreibt weiterhin.
- Listen (Seiten, Blog, Katalog) zeigen **eine Zeile pro Übersetzungsgruppe**; Locale-Badges öffnen die jeweilige Fassung. Technisch bleiben es weiterhin eigene Page-Zeilen.
- Navigation: ein Formular mit Locale-Tabs – Labels/Ziele pro Sprache, gemeinsame Hierarchie (Parent) über die Default-Locale.
- „Übersetzung anlegen“ wählt Ziel-Locale, Quell-Locale und ob der Blockbaum kopiert wird.
- Der Builder zeigt die Locale der geöffneten Page unmissverständlich (Chrome-Badge).
- Account-Bereich: Sprachumschalter über `?locale=` auf der aktuellen Account-URL.
- API-/Flash-Fehlermeldungen folgen `users.ui_locale`; `error.code` bleibt sprachneutral.

## 10.9 Plugins

First-Party-Plugins müssen locale-fähig sein, sobald sie Inhalte oder Public-Routen haben:

- Formulare: `locale` am Datensatz, Danke-Seite in derselben Locale
- Blog: Beitrag = Page mit `type=post` und Pfad `/blog/{slug}`; Archiv-Route `/blog` bzw. `/{locale}/blog` (Plugin `nexis/blog`)
- Katalog: analog Blog mit `type=product` unter `/catalog/…`
- Redirects: Quellpfad inkl. Locale-Prefix bzw. Domain
- Consent-Banner: Texte aus Plugin-Lang oder Site-Settings pro Locale (`LocalizedMap` / Locale-Tabs)

Neue Plugin-Tabellen mit nutzerlesbarem Text nutzen entweder:

1. **Seiten-Modell** (`pages` + `translation_group_id`) für lange Inhalte, oder
2. **`LocalizedMap`** (JSON-Map Locale→String im Datensatz + Locale-Tabs im Admin, wie Consent).

Ein globales Textfeld ohne Locale ist ein Dokumentationsverstoß.

## 10.10 Cache und Performance

Full-Page-Cache-Key enthält `site_id + locale + path + snapshot_hash`. Publish einer Locale invalidiert nur diese Fassung plus Sitemap und Switcher-Fragmente.

## 10.11 Nicht in Phase 1

- Automatische maschinelle Übersetzung
- RTL-Layout-Engine (Locale darf `dir="rtl"` setzen; Theme muss Tokens liefern, kein Kern-Zwang)
- Pro-Locale-Themes (ein Theme pro Site; Texte und Tokens reichen)
- Community-Übersetzungsworkflow / Crowdin-Anbindung
