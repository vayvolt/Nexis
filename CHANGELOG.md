# Changelog

Alle wesentlichen Änderungen an Nexis. Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), Versionierung nach SemVer.

## [Unreleased]

## [0.3.0] – 2026-09-26

### Added

- Block-Patterns und Medien-Ordner (Admin + Builder)
- Core-Blöcke: Galerie (`core/gallery`), Video/Embed YouTube/Vimeo (`core/embed`), Tabelle CSV (`core/table`)
- Unpublish im Builder (Snapshot zurücknehmen) inkl. Webhook `page.unpublished`
- Medien-Fokuspunkt (`focus_x`/`focus_y`, object-position) und geplante Unpublish (`unpublish_at`)
- Globale Inhalte: Header-CTA + Footer-Teaser (`/admin/globals`, Theme-Slots)
- Bildvarianten-UI (Original/Thumb/WebP auflisten, neu erzeugen)
- Review-Workflow: Status `in_review`, Recht `content.page.submit_review` (Redakteure ohne Publish), Freigeben/Ablehnen im Builder, Webhooks `page.review_submitted` / `page.review_rejected`
- Builder als zentrale Arbeitsfläche: Seite/SEO, Inhalt, Aktionen, Zeitplanung und Erweitert in einem Flow; getrennte Seitenbearbeitung entfällt
- Redaktionskommentare und Aufgaben im Builder (`page_editorial_items`)
- SEO im Builder: Focus-Keyword, Checkliste/Score, Such- und Social-Vorschau (`focus_keyword`)
- Redirects: CSV-Import und -Export (`nexis/redirects`); 404-Protokoll speichert wieder (PDO-Named-Parameter)
- Structured Data im Core: JSON-LD (`WebPage`/`FAQPage`/`WebSite`/`Organization`) + Event `StructuredDataBuilding` für Plugins; FAQ aus `nexis/faq/*`; Produkt/Offer und Block `nexis/product/details` im Plugin `nexis/catalog`
- Rollen-Templates out of the box: **Redakteur** (`editor`) und **SEO** (`seo`) neben Website-Admin/Mitglied; neues Recht `content.page.seo` (SEO-Rolle ohne Blockbearbeitung); Redirects-Plugin vergibt `redirects.manage` auch an SEO
- Öffentliche Fehlerseiten (404/500) und Admin-500 als HTML statt nur JSON; Theme-Templates `error` / `maintenance`
- Wartungsmodus unter Einstellungen → Betrieb (`site.maintenance.*`, 503-Seite, CMS-Bypass)
- Log-Viewer unter System → Logs (`/admin/logs`, liest `storage/logs/app.log`)
- Health-Dashboard unter System → Health (`/admin/health`: Queue-Tiefe, Disk, PHP, DB)

### Changed

- Boot: Plugin-/Theme-Katalog-Sync und Asset-Publish überspringen unveränderte Einträge; Theme-Resolve ohne Katalog-Resync pro Request
- Page-Cache: Ablaufprüfung in UTC (vorher sofortiger Miss bei Europe/Berlin)
- Admin-Seiten öffnen direkt im Builder (Titel, Meta, Robots beim Speichern)
- Seiten löschen entfernt die gesamte Übersetzungsgruppe (alle Sprachen) inkl. Modal-Bestätigung; Soft-Delete gibt den URL-Pfad frei (Recreate ohne FK-Fehler)
- System-Rolle „Redaktion“ → „Redakteur“; Editor-Defaults ohne Theme-/Audit-/Publish-Rechte
- Eigenes Admin-Konto: Admin-Rechte nicht selbst änderbar; mindestens ein Website-/Plattform-Admin bleibt

## [0.2.1] – 2026-09-20

Erstes öffentliches Distributions-Release (ZIP über Directory-Download).

### Enthalten

- Self-hosted Homepage-Builder (eine Website pro Installation)
- Web-Installer, Block-Builder, Medien, Menüs, Benutzer/Rollen, 2FA, Queue, Mail
- Admin-UI und öffentliche Oberfläche (de/en)
- Themes: **Nexis** (Install-Default), Atelier, Editorial
- First-Party-Plugins: Blog, Katalog, Consent, Forms, Redirects
- Schema über `database/core_schema.sql`
- Optionaler Plugin-Marktplatz (Directory: `https://nexis.vayvolt.de`)
- Update-Check mit anonymer Install-ID (eine Installation = ein Statistik-Datensatz)

### Hinweise

- Document Root = Projektroot; `.env` aus `.env.example` bzw. Web-Installer
- Queue-Worker im Betrieb: `php bin/queue-work.php`
