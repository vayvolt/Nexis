# Drittanbieter- und SDK-Inventar (Betreiber)

Was eine typische Nexis-Site **optional oder bedingt** nach außen kontaktiert. Kein Auto-Inventar im CMS – diese Liste als Ausgangspunkt für Datenschutz und AV-Pflege.

Siehe auch: [dpa-subprocessors.md](dpa-subprocessors.md), [accessibility.md](accessibility.md), Consent-Admin `/admin/consent`.

## Kern (ohne Extra-Konfiguration)

| Komponente | Wann | Daten / Hinweis |
|---|---|---|
| Hosting / DB / SMTP | Immer (Ihre Infrastruktur) | Server-Logs, Konten, Formulare, Mails – **Ihr** Auftragsverarbeiter |
| Session / CSRF-Cookies | Jeder Besuch mit Session | Technisch notwendig; in der Privacy nennen |

## Optional / nach Konfiguration oder Theme

| Komponente | Wann aktiv | Consent / Hinweis |
|---|---|---|
| Google Analytics / GTM / Ads | IDs unter `/admin/consent` | Opt-in vor Tag-Load; CSP wird erweitert |
| OpenStreetMap (Standort-Block) | Nutzer klickt „Karte laden“ | Kein Auto-Embed; OSM in Privacy erwähnen |
| Google Fonts (First-Party-Themes) | Theme-CSS `@import` fonts.googleapis.com | Verbindungsaufbau zum Font-CDN beim Seitenaufruf; Alternative: Fonts selbst hosten |
| Directory Update-Check | Marketplace-URL / Update-Check | Anonyme Install-Metadaten; in Privacy erwähnen falls aktiv |
| Webhooks / API-Clients | Von Ihnen konfiguriert | Ziel-URLs und Payloads selbst inventarisieren |
| Dritt-Plugins / Custom-HTML | Installation / Blöcke | Tracking-Skripte nur mit Consent und Inventar-Eintrag |

## First-Party-Theme-Fonts (Google Fonts, Laufzeit-CDN)

| Theme | Familien (typisch) |
|---|---|
| `nexis/nexis` | DM Sans |
| `nexis/nord` | Space Grotesk |
| `nexis/atelier`, `nexis/atelier-warm` | Source Sans 3, Source Serif 4 |
| `nexis/editorial` | IBM Plex Sans, IBM Plex Serif |

Lizenzen der Schriftfamilien: jeweils SIL Open Font License (OFL) beim Anbieter; Auslieferung aktuell über Google Fonts CDN (siehe Theme-`assets/theme.css`).

## Check vor Go-Live

- [ ] Alle Zeilen oben für Ihre Installation durchgehen (aktivieren / streichen)
- [ ] Einträge in Datenschutz + [Unterauftragsverarbeiter-Tabelle](dpa-subprocessors.md) übernehmen
- [ ] Keine zusätzlichen Skripte in Custom-CSS/HTML ohne Inventar + Consent
