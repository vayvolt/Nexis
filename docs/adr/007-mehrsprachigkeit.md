# ADR 007 – Eine Page pro Locale, verknüpft über translation_group_id

**Status:** angenommen  
**Datum:** 2026-09-07

## Kontext

Mehrsprachige Homepages brauchen eigene Slugs, eigene SEO-Texte und oft abweichende Blöcke (andere Teaser, andere Rechtsseiten). Ein JSON-Feld `translations` in einer einzigen Page vermischt Draft-Status und macht unabhängiges Publish unmöglich.

## Entscheidung

Jede sprachliche Fassung ist eine eigene `pages`-Zeile mit eigenem Dokument und Snapshot. Fassungen derselben logischen Seite teilen `translation_group_id`. URL-Strategie ist ein Site-Setting (`prefix` Default, `domain`, `none`). Inhalts-URLs werden nicht über `Accept-Language` geraten.

## Konsequenzen

- Seitenbaum und `parent_id` sind locale-rein.
- `hreflang` und Language-Switcher lesen die Gruppe und nur veröffentlichte Geschwister.
- Der Builder braucht „Übersetzung anlegen“ (Kopie optional).
- Mehr Zeilen in `pages`, dafür klare Transaktionen und Cache-Keys.
- **Ergänzung (kurze Labels):** Für Consent-Texte u. Ä. gilt parallel `LocalizedMap` (ein Datensatz, Locale→String-JSON + Admin-Tabs) – siehe [10.2](../10-mehrsprachigkeit.md#kurze-labels-in-einem-datensatz-localizedmap). Das ADR bleibt für lange Page-Inhalte verbindlich.
