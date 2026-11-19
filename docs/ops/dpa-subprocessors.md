# Auftragsverarbeitung und Unterauftragsverarbeiter

Kurzcheckliste für **Betreiber** einer Nexis-Installation. Kein Rechtsrat und kein Ersatz für AV-Verträge.

Siehe auch: [Sicherheit und Betrieb §6.10](../06-sicherheit-und-betrieb.md), [Go-Live](go-live.md), [Launch-Checklist-Status](../internal/14-app-launch-checklist-status.md).

## Rolle klären

| Rolle | Typisch bei Nexis |
|---|---|
| **Verantwortlicher** | Die Organisation, die die Website betreibt (Inhalte, Zwecke, Betroffenenrechte) |
| **Auftragsverarbeiter** | Hosting-Provider, E-Mail-/SMTP-Dienst, ggf. CDN, Backup-Ziel |
| **Nexis-Software** | Selbst gehostetes CMS; Vayvolt ist **nicht** automatisch Auftragsverarbeiter Ihrer Live-Site |

## AV-Vertrag (Art. 28 DSGVO) – Check

- [ ] Mit jedem Auftragsverarbeiter schriftlicher AV-Vertrag (oder Standardvertragsklauseln / Anbieter-DPA)
- [ ] Gegenstand, Dauer, Art und Zweck der Verarbeitung beschrieben
- [ ] Weisungsbindung und Vertraulichkeit geregelt
- [ ] TOMs des Auftragsverarbeiters geprüft (Zugriff, Verschlüsselung, Logging)
- [ ] Unterstützung bei Betroffenenrechten und Meldepflichten vereinbart
- [ ] Löschung/Rückgabe nach Vertragsende festgelegt
- [ ] Unterauftragsverarbeiter des Providers: Genehmigung/Widerspruchsrecht geklärt
- [ ] Drittlandtransfers (falls USA/andere): Transfermechanismus dokumentiert (z. B. SCC, Angemessenheitsbeschluss)

## Unterauftragsverarbeiter-Liste (öffentlich pflegen)

Führen Sie eine aktuelle Liste (z. B. Seite unter `/datenschutz` oder separates Dokument) mit mindestens:

| Dienst | Zweck | Datenkategorien | Sitz / Transfer |
|---|---|---|---|
| Hosting (Name) | Betrieb der Website, DB, Dateien | Server-Logs, Formular-/Kontodaten | … |
| SMTP / Mail | Transaktionsmails | E-Mail-Adresse, Name | … |
| Google Analytics / GTM / Ads *(optional)* | Statistik / Marketing | Online-Kennungen, nach Consent | … |
| Backup-Ziel *(falls extern)* | Sicherung | DB-/Medienkopien | … |

Nexis speichert keine zentrale Vendor-Liste im Produkt – die Pflege liegt beim Betreiber.

## Was Nexis technisch unterstützt

- Datenschutz-/Impressum-/AGB-Vorlagen (Platzhalter anpassen)
- Consent-Banner für konfigurierte Google-IDs (Opt-in vor Tags)
- Konto: Datenexport (`/account/export`) und Selbstlöschung
- Admin: Benutzer löschen/anonymisieren; Formular-Einreichungen CSV-Export + Löschen (`nexis/forms`)
- Audit-Log (u. a. Registrierungs-Annahme, Datenexport)

## Was außerhalb des CMS bleibt

- AV-Verträge und Rechtsberatung
- Vollständige Zwecke/Rechtsgrundlagen in der Datenschutzerklärung
- Inventar aller SDKs/Plugins mit Tracking → Ausgangspunkt [vendor-inventory.md](vendor-inventory.md)
- OSM-Karten: Embed erst nach Klick („Karte laden“); in der Datenschutzerklärung OpenStreetMap erwähnen
