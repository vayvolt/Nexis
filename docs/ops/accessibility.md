# Barrierefreiheit (A11y)

Ziel und Praxis für Nexis-Themes und Betreiber-Inhalte. Kein Zertifikat, kein Audit-Ersatz.

## Zielniveau

- **Ziel:** [WCAG 2.2](https://www.w3.org/TR/WCAG22/) **Stufe AA** für öffentliche Seiten und Konto-UI, soweit Theme und Core das steuern.
- **Kontrast:** Text und UI-Elemente idealerweise ≥ **4,5:1** (Normaltext) bzw. ≥ **3:1** (große Schrift / UI-Komponenten) gegen den Hintergrund.
- First-Party-Themes (Nexis, Nord, Atelier, Editorial) sind mit diesem Ziel gestaltet; Custom-CSS und hochgeladene Medien können das brechen.

## Was Nexis mitbringt

- Skip-Link „Zum Inhalt“ in Theme-Templates
- Alt-Text-Pflicht für Bilder (Medienbibliothek + Image-Block)
- `:focus-visible`-Umrisse in First-Party-Themes (Links, Buttons, Nav, Formulare)
- Locale-Menü: Escape schließt und setzt Fokus zurück auf Summary
- Consent-Banner: Fokus auf erste Aktion, Escape schließt/Ablehnen, Fokus zurück zum Öffner; `:focus-visible` auf Buttons/Links
- Tastaturbedienung im Builder (Shortcuts)

## Checks vor Go-Live (Betreiber)

- [ ] Theme-Farben: Live-Kontrast unter Admin → Theme → Farben prüfen; Custom-CSS zusätzlich mit axe/WAVE
- [ ] Skip-Link per Tab sichtbar und springt zum Hauptinhalt
- [ ] Bilder mit aussagekräftigem Alt (oder bewusst dekorativ leer, wo erlaubt)
- [ ] Formulare: Labels, Fehlermeldungen, Fokus nach Submit
- [ ] Consent-Banner und Dialoge per Tastatur bedienbar (Tab, Escape; Fokus sichtbar)
- [ ] Optional: Screenreader-Smoke (NVDA/VoiceOver) auf Start, Kontakt, Konto

## Bewusst offen

- Kontrast-Live-Check im Theme-Admin deckt nur die vier Branding-Token (hex/rgb) ab – kein Seiten-Audit
- Keine WCAG-Zertifizierung der Distribution
- Plugin-/Custom-HTML-Inhalte liegen in der Verantwortung des Betreibers

Siehe auch: [Go-Live](go-live.md), [Launch-Checklist-Status](../internal/14-app-launch-checklist-status.md).
