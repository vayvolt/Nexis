# 05 Individualisierung und Theming

Individualisierung ist eine eigene Säule neben Plugins. Ein Kunde soll Marke, Layout-Rahmen und Inhalte anpassen können, ohne PHP zu schreiben. Entwickler sollen Themes wiederverwenden, nicht pro Projekt forken.

## 5.1 Schichten der Anpassung

Von unten nach oben, spätere Schicht gewinnt:

1. **Core-Defaults** – neutrale Tokens, Fallback-Templates
2. **Theme** – Layout, Typografie, Block-Styles, Slots
3. **Site-Branding** – Farben, Logo, Schriften, Radius (Tokens)
4. **Page-Overrides** – lokale Block-Styles und Sektionsvarianten
5. **Plugin-Injektion** – nur in deklarierte Slots

Redakteure arbeiten auf 3 und 4. Entwickler auf 2 und 5. Der Kern bleibt auf 1.

## 5.2 Theme-Manifest `theme.json`

```json
{
  "id": "nexis/atelier",
  "name": "Atelier",
  "version": "1.0.0",
  "compatibleCore": "^1.0",
  "slots": ["header", "footer", "after-content", "admin.preview-chrome"],
  "templates": {
    "layout": "templates/layout.twig",
    "page": "templates/page.twig",
    "404": "templates/404.twig"
  },
  "tokens": "tokens.json",
  "breakpoints": ["sm", "md", "lg", "xl"]
}
```

Ein Theme darf Templates, CSS, begrenztes JS und Token-Defaults liefern. Es darf keine SQL-Migrationen und keine PHP-Service-Provider enthalten. Logik, die Daten braucht, gehört in ein Plugin.

First-Party-Themes im Repo (Auszug):

| ID | Charakter |
|---|---|
| `nexis/nexis` | Neutral Zinc, DM Sans (Install-Default) |
| `nexis/atelier` | Warm, serifenbetont |
| `nexis/editorial` | Redaktionell, klar |
| `nexis/nord` | Weiß/Grau/Blau, Space Grotesk (nur Repo, nicht im Release-ZIP) |

Layout-Token u. a. `layout.footer.position` (`split` / `center` / `stack` / `reverse`) sowie `layout.footer.showSiteName` / `layout.footer.showThemeName` sind im Theme-Admin pflegbar.

## 5.3 Design-Tokens

`tokens.json` beschreibt das Design systemweit:

```json
{
  "color.brand.primary": "#1b4d3e",
  "color.brand.accent": "#d4a017",
  "color.surface": "#ffffff",
  "color.text": "#1a1a1a",
  "font.sans": "\"Source Sans 3\", system-ui, sans-serif",
  "font.scale.h1": "clamp(2rem, 4vw, 3.5rem)",
  "space.unit": "0.25rem",
  "radius.md": "0.5rem",
  "shadow.card": "none",
  "layout.nav.position": "right",
  "layout.pageTitle": "hide",
  "layout.max": "72rem",
  "brand.showSiteName": "true",
  "brand.logoInNav": "false"
}
```

Zusätzliche Layout-/Brand-Tokens (Site-Overrides im Theme-Admin):

| Token | Werte | Wirkung |
|---|---|---|
| `layout.nav.position` | `right` \| `left` \| `below` | Position der Hauptnavigation relativ zur Marke |
| `layout.footer.position` | `split` \| `center` \| `stack` \| `reverse` | Anordnung Footer-Menü / Meta |
| `layout.footer.showSiteName` | `true` \| `false` | Site-Titel in der Footer-Meta-Zeile |
| `layout.footer.showThemeName` | `true` \| `false` | Theme-Name in der Footer-Meta-Zeile |
| `layout.pageTitle` | `hide` \| `show` | Seiten-Titel über dem Inhaltsbereich |
| `layout.max` | CSS-Länge | Maximale Inhaltsbreite |
| `brand.showSiteName` | `true` \| `false` | Site-Name neben Logo |
| `brand.logoInNav` | `true` \| `false` | Logo in der Nav statt nur in der Brand-Zeile |

Menü-Handles: `primary` (Header), `footer` (Footer-Nav im Theme). Admin: `/admin/menus?handle=primary\|footer`.

Regeln:

- Token-Namen sind dot-notiert und stabil. Umbenennen ist ein Major des Themes.
- Site-Overrides speichern nur Differenzen, nicht das ganze Set.
- Beim Aktivieren eines Themes werden Layout-/Farb-/Logo-/Schrift-Overrides zurückgesetzt; Custom CSS bleibt.
- Das Frontend bekommt Tokens als CSS Custom Properties auf `:root`.
- Blöcke nutzen Tokens, keine hardcodierten Hex-Werte im Plugin-CSS.

## 5.4 Templates und Vererbung

Twig-Layouts definieren Slots. First-Party-Themes (Nord, Atelier, Editorial) rufen u. a.:

| Slot | Ort |
|---|---|
| `theme.head` | `<head>` |
| `theme.header-actions` | Header neben Nav (Warenkorb-Badge etc.) |
| `theme.footer-links` | Footer-Linkzeile |
| `theme.footer` | vor `</body>` |

```twig
{# templates/layout.twig (Beispiel) #}
<!DOCTYPE html>
<html lang="{{ site.locale }}">
<head>
  {{ slot('theme.head') }}
  <link rel="stylesheet" href="{{ theme_asset('theme.css') }}">
</head>
<body>
  <header>
    {# … Nav … #}
    {{ slot('theme.header-actions') }}
  </header>
  <main>{{ content }}</main>
  {{ slot('theme.footer') }}
</body>
</html>
```

Plugins registrieren Slot-Views mit Priorität. Mehrere Beiträge pro Slot sind erlaubt und werden sortiert. Es gibt keinen globalen HTML-Filter, der fremdes Markup umschreibt.

## 5.5 Builder und Theme

Der Builder zeigt eine **themennahe Preview** (dieselben Tokens und Layout-Rahmen), nicht unbedingt jedes dekorative Extra. Gründe:

- Redakteure sehen Abstände und Typografie korrekt.
- Theme-JS, das auf Live-DOM angewiesen ist, darf die Preview nicht zerlegen.

Dafür stellt das Theme denselben `page`-Rahmen bereit (Tokens, Header/Nav). Die Admin-Vorschau unter `/admin/pages/{id}/preview` rendert den Entwurf im Theme inkl. Preview-Leiste; fehlt Theme-Rendering, fällt sie auf ein Minimal-Chrome zurück. Slot-Name `admin.preview-chrome` bleibt für künftige Theme-Overrides reserviert.

## 5.6 Responsive Verhalten

Breakpoints sind themenweit definiert. Block-`style` darf pro Breakpoint Token-Referenzen oder begrenzte CSS-Werte enthalten. Beliebige CSS-Strings von Redakteuren sind in Phase 1 **nicht** erlaubt. Stattdessen: Spacing-Stufen, Farben aus der Token-Palette, Ausrichtung, Sichtbarkeit.

Ein optionales Permission-Flag `theme.custom_css` gibt Agenturen ein Site-weites CSS-Feld. Das CSS wird beim Speichern und Ausliefern sanitisiert (kein HTML/`</style>`-Breakout, kein `@import`/`expression`/`javascript:`). Es wird zusammen mit den Token-Variablen im Seiten-`<style>` ausgegeben (CSP erlaubt dafür `'unsafe-inline'` bei Styles). Eine strikte Property-Allowlist ist für spätere Versionen vorgesehen.

## 5.7 Mehrsprachigkeit und Lokales

- Inhaltssprache hängt an Site-Locales und Page-Fassungen, nicht am Theme. Siehe [10 Mehrsprachigkeit](10-mehrsprachigkeit.md).
- Themes liefern nur eigene UI-Strings (`lang/{locale}.json`) und den Slot für den Language-Switcher.
- Blockinhalte werden nicht im Theme übersetzt.
- Ein Theme pro Site; keine theme-pro-Locale-Variante in Phase 1. `dir="rtl"` darf das Theme anhand der Locale setzen.

## 5.8 Individualisierung ohne Fork – Leitfaden

| Wunsch | Mechanismus |
|---|---|
| Logo, Farben, Schriften | Site-Tokens |
| Anderer Footer | Theme-Slot oder Child-Template |
| Neuer Block | Plugin |
| Zusätzliches Menü im Header | Plugin + Slot `header` |
| Komplett anderes Layout | Neues Theme, Tokens wiederverwenden |
| Kunden-CSS | `theme.custom_css` (rechtlich/technisch bewusst) |

Child-Themes sind erlaubt: `theme.json` mit `"extends": "nexis/atelier"` überschreibt selektiv Templates und Tokens. PHP bleibt tabu.

## 5.9 Asset-Pipeline

- Theme- und Plugin-Assets werden beim Aktivieren nach `assets/` im Document Root mit Content-Hash publiziert.
- URLs sind stabil über den Hash im Dateinamen.
- Kein Laufzeit-SCSS-Compiler in Production. Build passiert bei Deploy oder Plugin-Aktivierung.
