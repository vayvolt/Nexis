# ADR 004 – PSR und Symfony-Komponenten, kein Full-Stack-Framework

**Status:** angenommen  
**Datum:** 2026-09-07

## Kontext

Laravel oder Symfony Full-Stack beschleunigen das Admin, binden aber Plugin-Autoren an Rahmenkonventionen des Frameworks und erschweren einen schlanken Kernel.

## Entscheidung

HTTP, Container, Events und Logging über PSR-7/11/14/15/3. Symfony-Komponenten dürfen einzeln genutzt werden. Die Plugin-API ist nexis-eigen.

## Konsequenzen

- Mehr Gerüst in Phase 0.
- Plugin-Autoren lernen ein kleines, dokumentiertes SDK statt „ganzes Laravel“.
- Framework-Upgrades betreffen nicht die Manifest-Verträge.
