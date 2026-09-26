# ADR 001 – Modularer Monolith

**Status:** angenommen  
**Datum:** 2026-09-07

## Kontext

Nexis muss Plugins laden, Seiten rendern und einen Editor anbieten. Microservices (Content, Media, Auth getrennt) würden Netzwerklatenz, verteilte Transaktionen und eine schwierige lokale Dev-Umgebung bedeuten.

## Entscheidung

Eine Anwendung, ein Deployable, eine MariaDB. Fachliche Trennung über Bounded Contexts und Plugin-Verträge, nicht über Prozessgrenzen.

## Konsequenzen

- Publish bleibt eine lokale Transaktion (Revision, Snapshot, Page, Cache-Event).
- Plugins laufen im selben Prozess; Isolation ist fail-soft, keine Hardware-Sandbox.
- Später können lesende Public-Renderer oder Medien-CDN ausgelagert werden, ohne das Modell zu kippen.
