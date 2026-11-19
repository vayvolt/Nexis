# ADR 002 – Seiteninhalt als versioniertes JSON

**Status:** angenommen  
**Datum:** 2026-09-07

## Kontext

Blöcke sind hierarchisch, haben Breakpoint-Styles und plugin-spezifische Props. Ein vollständig relationales Blockmodell (Zeile pro Block, Adjazenzliste) macht Revisionen und Schema-Entwicklung teuer.

## Entscheidung

Der Editor speichert ein JSON-Dokument pro Revision. Beim Veröffentlichen entsteht ein unabhängiger Snapshot. Relationale Tabellen halten Metadaten (Pfad, Status, SEO), nicht den Baum.

## Konsequenzen

- Schema-Validierung vor jedem Save ist Pflicht.
- Partielles SQL-Update einzelner Blöcke entfällt; Konflikte laufen über Dokument-Hash.
- Suche braucht materialisiertes `search_text` beim Publish.
- Schema-Version im Dokument ermöglicht Blockformat-Migrationen unabhängig von SQL.
