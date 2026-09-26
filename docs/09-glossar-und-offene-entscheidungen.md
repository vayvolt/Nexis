# 09 Glossar und offene Entscheidungen

| Begriff | Bedeutung |
|---|---|
| **Kern / Kernel** | Boot, HTTP, Auth, Plugin-Lader, Theme-Resolver. Keine Fachmodule wie Blog oder Shop. |
| **Site** | Die eine Website dieser Installation (Domain, Theme, Inhalte). |
| **Tenant** | Organisatorische Hülle 1:1 zur Site (kein Multi-Mandant). |
| **Page** | Navigierbarer Inhalt. Der Blockbaum liegt in Revisionen, nicht in der Page-Zeile. |
| **Revision** | Gespeicherter Editor-Zustand. Beliebig viele pro Page. |
| **Snapshot** | Unveränderliche Veröffentlichung. Die Live-Site liest nur Snapshots. |
| **Block** | Atom im Builder (`vendor/name`) mit JSON-Schema, Props und Renderer. |
| **Plugin** | Paket mit Manifest und Service Provider. Erweitert Blöcke, Routen, Slots, Jobs. |
| **Theme** | Layout, Tokens, Templates. Kein PHP, keine Migrationen. |
| **Token** | Benannte Design-Variable (`color.brand.primary`), überschreibbar pro Site. |
| **Slot** | Benannte Einschubstelle in Theme oder Admin, in die Plugins Views legen. |
| **Extension Point** | Offizieller Haken für Plugins (Block, Route, Slot, Event, …). |
| **Policy** | Autorisierungsregel für eine Aktion an einem Objekt. |
| **Locale** | BCP-47-Code der Inhaltssprache (`de`, `en`, `de-CH`). |
| **translation_group_id** | Gemeinsame ID aller sprachlichen Fassungen einer logischen Seite. |
| **Inhalts-Locale** | Sprache der öffentlichen Seite, unabhängig von der Admin-Oberfläche. |
| **LocalizedMap** | JSON Locale→String in einem Datensatz (kurze Labels); Admin mit Locale-Tabs. |
| **Fail-soft** | Plugin-Fehler ersetzen nur den betroffenen Teil, nicht die ganze Response. |

## Offene Entscheidungen

Erledigte bzw. für 0.2 festgezogene Punkte:

1. Builder-UI: Vanilla-JS (ohne Build-Schritt) für Drag-and-Drop + Schema-Forms; Vue/React optional später.
2. First-Party-Plugins liegen unter `plugins/nexis/`: forms, redirects, consent, blog, catalog; siehe [04 Plugin-System](04-plugin-system.md#412-first-party-vs-third-party).
3. Redis: vorerst Datei/DB-Cache; Redis optional später hinter denselben Ports.
4. Abrechnungs-/Lizenzmodell: technisch durch `tenants` vorbereitet, Produkt ist Single-Site.
5. ~~Lizenz des Kerns~~ → **GPL-2.0-or-later** (siehe `LICENSE`).
6. REST-API + API-Tokens: spezifiziert in [07](07-api-und-schnittstellen.md), **noch nicht umgesetzt** (nicht in 0.2).

Noch offen für spätere Versionen:

- Bezahl-Marktplatz / kommerzielle Listings (Phase-1-Directory: nur freie Plugins, siehe [11](11-plugin-marktplatz.md))
