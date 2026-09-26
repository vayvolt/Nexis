# 01 Produktvision und Ziele

## 1.1 Vision

**Nexis** ist ein **individualisierbarer Homepage-Builder** und ein Produkt von **Vayvolt**. Der Kern steht unter der **GNU GPL v2 or later**. Redakteure bauen Seiten visuell aus Blöcken. Agenturen und Entwickler erweitern das System über Themes und Plugins, ohne den Kern zu forken.

Eine Installation verwaltet **genau eine Website**. Marke, Seiten, Plugins und Rechte gehören zu dieser einen Site. Individualisierung läuft über Theme, Tokens und Plugins – ohne Fork des Kerns.

## 1.2 Problem, das gelöst wird

Klassische CMS-Systeme erzwingen entweder starre Templates oder einen Fork des Kerns, sobald ein Kunde Sonderwünsche hat. Page Builder ohne saubere Plugin-Verträge werden unwartbar. Nexis trennt deshalb drei Ebenen:

1. **Kern** – Routing, Auth, Website-Einstellungen, Seiten, Medien, Plugin-Lader.
2. **Individualisierung** – Theme, Design-Tokens, Site-Branding, Seiten-Overrides.
3. **Erweiterung** – Plugins für Blöcke, Routen, Admin-Slots, Jobs, Integrationen.

## 1.3 Produktumfang

### Im Kern

- Eine Website pro Installation (technisch `sites`/`site_id`, siehe ADR 008)
- Seiten, Navigation, Medienbibliothek
- Visueller Block-Builder inkl. Revisionen und Entwurf/Live
- Benutzer, Rollen, feingranulare Rechte
- Theme-Engine mit Tokens und Slots
- Plugin-Manager mit Lebenszyklus und Signaturprüfung
- REST-API für Admin und ausgewählte Public-Endpunkte (**geplant**, nicht Bestandteil von 0.2)
- Mehrsprachigkeit auf Site-Ebene (eigene Page pro Locale, URL-Strategien, hreflang)
- Basis-SEO (Title, Description, Canonical, Open Graph, Sitemap, JSON-LD WebPage/FAQ; Product via Catalog-Plugin)
- FAQ-/Accordion-Blöcke und Standort-Block (OpenStreetMap) im Core-Builder

### Als First-Party-Plugins geplant

- Formulare und Lead-Erfassung (`nexis/forms`)
- Cookie- und Consent-Banner (`nexis/consent`) inkl. Google Consent Mode v2 (GA4 / GTM / Ads)
- Blog / News (`nexis/blog`) – Beiträge als `pages.type=post`, Archiv `/blog`, Admin `/admin/blog`
- Redirects und 404-Protokoll (`nexis/redirects`)
- Einfacher Produktkatalog (`nexis/catalog`) – Produkte als `pages.type=product`, Archiv `/catalog`, Admin `/admin/catalog` (kein Warenkorb)

### Explizit nicht im Kern

- Vollständiger E-Commerce (Warenkorb, Zahlung, Steuer)
- Community / Foren
- Headless-only Betrieb ohne Admin-UI (kann später als Modus kommen)
- Visueller Theme-Editor auf Figma-Niveau in Phase 1

## 1.4 Zielgruppen

| Rolle | Primärer Nutzen |
|---|---|
| Redakteur | Seiten ohne Entwickler bauen, Inhalte versionieren, Medien pflegen |
| Agentur | Pro Kunde Theme + wenige Plugins, kein Fork |
| Plugin-Entwickler | Stabile Verträge, Composer-Paket, Hooks statt Core-Patches |
| Betreiber | Eine Codebasis, eine Website, nachvollziehbare Rechte und Backups |

## 1.5 Qualitätsziele

| Ziel | Maßstab |
|---|---|
| Erweiterbarkeit | Neue Blocktypen und Admin-Slots ohne Core-Änderung |
| Isolierung | Plugin-Fehler dürfen den Render der restlichen Seite nicht hart beenden |
| Individualisierbarkeit | Markenwechsel über Tokens, ohne Template-Fork |
| Performance | TTFB einer gecachten öffentlichen Seite < 200 ms auf Referenzhost |
| Sicherheit | Default-Deny Rechte, CSRF, CSP, prepared statements, Upload-Whitelist |
| Upgrade-Sicherheit | SemVer für Core und Plugin-API; Breaking Changes nur mit Migration |
| Beobachtbarkeit | Strukturierte Logs, Request-ID, Audit für Inhalte und Plugin-Aktionen |

## 1.6 Nicht-Ziele der ersten drei Phasen

- Marktplatz mit **Bezahlplugins** (öffentliches Directory für freie Plugins: siehe [11](11-plugin-marktplatz.md))
- Eigene Hosting-Plattform
- Mobile Native Apps
- Echtzeit-Kollaboration mehrerer Redakteure auf derselben Seite (nur Locking)

## 1.7 Erfolgskriterien

Das System gilt als tragfähig, wenn:

1. Eine neue Installation in unter 15 Minuten aus Theme + Basisinhalten entsteht.
2. Ein Dritt-Plugin einen eigenen Block und einen Admin-Menüpunkt registrieren kann, ohne Core-Dateien zu ändern.
3. Design-Tokens eine Site farblich und typografisch umstellen, ohne PHP zu ändern.
4. Ein fehlgeschlagenes Plugin die restliche Site nicht unzustellbar macht.
5. Inhalte über Revisionen reproduzierbar auf einen früheren Stand zurückgesetzt werden können.
