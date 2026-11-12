# Offene Punkte

Stand aus [docs/internal/13-roadmap-post-0.2.md](docs/internal/13-roadmap-post-0.2.md) und [docs/07-api-und-schnittstellen.md](docs/07-api-und-schnittstellen.md).

## Priorität (empfohlen)

Führende Planung bis **1.0** (Kern, Plugins optional): intern [`docs/internal/17-roadmap-to-1.0.md`](docs/internal/17-roadmap-to-1.0.md).

1. Papierkorb / Recover (0.6) — nächster Meilenstein nach 0.5.0
2. Seitenhierarchie-UI (0.6)
3. SSO und/oder Passkeys (0.7)
4. Staging↔Prod Content-Sync (0.8)
5. Shop (`nexis/shop`) – nur bei Demand, **kein** 1.0-Gate

## REST-API – noch offen

- [x] `POST /api/v1/admin/pages/{id}/translations` (+ `GET …/alternates`)
- [x] `POST /api/v1/admin/pages/{id}/revert`
- [x] `GET /api/v1/admin/plugins` + `POST …/{vendor}/{name}/enable` bzw. `/disable`
- [x] `GET` + `PATCH /api/v1/admin/theme/tokens`
- [x] `GET /api/v1/admin/blocks`
- [x] CORS für Browser-Clients (`API_CORS_ORIGINS`)

## Backups – noch offen

- [x] Restore aus der Admin-UI (CLI bleibt: `php bin/restore.php`)
- [ ] Inkrementelle Backups
- [ ] Off-Site-Ziele (S3 / FTP)
- [x] Auswahl Medien beim Restore (DB immer; Medien optional)

## Inhalte & Editor

- [x] Formular-Builder mit Bedingungen (`nexis/forms` 1.1: Definitionen, Felder, Sichtbarkeit, Admin-Builder)
- [ ] Feineres Scheduling jenseits `publish_at` / `unpublish_at`

## SEO & Marketing

- [ ] A/B-Test-Slots / einfache Experiments (`nexis/experiments` – lokal, nicht im öffentlichen Release)
- [ ] Newsletter-Anbindung (Mailchimp/Brevo) als Plugin

## Benutzer & Zugang

- [ ] SSO / OAuth / SAML
- [ ] Passkeys / WebAuthn neben TOTP
- [x] Rechte-UI / Rollen-Matrix (Rolle × Permission im Admin unter `/admin/roles`)
- [ ] Kundenkonto-Ausbau (Profilfelder, Bestellhistorie bei Shop)

## Entwickler & Integrationen

- [ ] GraphQL neben REST (optional nach REST-Abschluss)
- [ ] CLI-Installer / `nexis`-CLI
- [ ] Staging ↔ Production Sync (nur Inhalte)
- [ ] Webhook-UI-Events erweitern (MediaUploaded, UserCreated, …)
- [ ] Plugin-Scaffold-Generator

## Shop / Marktplatz (später)

- [ ] Shop / Warenkorb / Checkout (`PaymentPort` vorhanden, Plugin fehlt)
- [ ] Lagerbestand / Bestellungen
- [ ] Bezahl-Marktplatz (bewusst später; Directory nur freie Plugins)
- [ ] Lizenzschlüssel für kommerzielle Plugins (nicht Phase 1)

## Infrastruktur / optional

- [ ] Weitere Locales jenseits de/en (UI/Contents)
- [ ] SVG-Uploads (sanitisiert)
- [ ] Redis (Cache/Queue) hinter Ports
- [ ] Builder Vue/React (optional; Vanilla-JS reicht)
- [ ] CSS-Property-Allowlist
- [ ] Headless-only-Modus

## Bewusst nicht / Nicht-Ziel

- Echtzeit-Kollaboration (Phase 1)
- Multi-Site
- Foren / Community, native Mobile Apps, Figma-Theme-Editor, Elementor-Stil, Chat/CRM im Core, AI-Textgenerator im Core
