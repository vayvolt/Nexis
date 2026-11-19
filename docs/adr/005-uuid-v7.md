# ADR 005 – UUID v7 als Primärschlüssel

**Status:** angenommen  
**Datum:** 2026-09-07

## Kontext

Auto-Increment leakt Bestände und kollidiert bei Site-Export/Import. Zufällige UUID v4 fragmentiert InnoDB-Indizes.

## Entscheidung

Öffentliche und interne Primärschlüssel sind UUID v7 (`CHAR(36)`). Zeitliche Sortierung bleibt erhalten, IDs sind API-fähig.

## Konsequenzen

- 36-Byte-Keys statt INT; akzeptiert für Klarheit und Portabilität.
- Generator zentral im Support-Namespace.
- Keine enumerierbaren Seitenzahlen in der Public-API.
