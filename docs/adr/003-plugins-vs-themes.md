# ADR 003 – Logik in Plugins, Darstellung in Themes

**Status:** angenommen  
**Datum:** 2026-09-07

## Kontext

Wenn Themes PHP und SQL mitliefern, entstehen unbezahlbare Forks. Wenn Plugins HTML-Layouts ersetzen, zerfällt die Markenkonsistenz.

## Entscheidung

- **Theme:** Twig, Tokens, Assets, Slots. Kein PHP-Provider, keine Migrationen.
- **Plugin:** Provider, Blöcke, Routen, Tabellen, Jobs.
- **Site-Branding:** nur Token-Differenzen und optionales Custom-CSS mit Permission.

## Konsequenzen

- Neue Fachfunktion = Plugin, auch intern (Formulare, Blog).
- Optische Varianten ohne Code = Tokens oder Child-Theme.
- Slot-Vertrag muss früh dokumentiert und stabil gehalten werden.
