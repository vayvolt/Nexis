# 08 Entwicklungsstandards

## 8.1 PHP 8.4

- `declare(strict_types=1);` in jeder PHP-Datei.
- Keine Short-Tags: `<?=` und `<?` sind unzulässig, nur `<?php`. Ausgabe mit `<?php echo`.
- Keine impliziten Nullable-Typen.
- Neue Domain-Objekte mit **asymmetric visibility**, wo Mutationen über Methoden laufen.
- Property Hooks nur für ableitbare oder validierende Zugriffe, nicht als versteckte I/O-Schicht.
- Enums für Status (`PageStatus`, `PluginState`, `LocaleUrlStrategy`).
- Extension `intl` ist Pflicht (ICU, BCP 47, Datums-/Zahlenformat).
- Nutzertexte nie fest im PHP-String für UI; Kern/Theme/Plugin-Kataloge nutzen JSON + ICU.
- `readonly` für IDs und Events.
- Keine `@param mixed` wo ein Contract existiert.

Beispiel:

```php
final class Page
{
    public function __construct(
        public private(set) PageId $id,
        public private(set) SiteId $siteId,
        public private(set) PageStatus $status,
        private string $title,
    ) {}

    public string $title {
        get => $this->title;
        set {
            $value = trim($value);
            if ($value === '') {
                throw new InvalidArgumentException('Titel darf nicht leer sein.');
            }
            $this->title = $value;
        }
    }
}
```

## 8.2 Namens- und Codekonventionen

- Namespace: `Nexis\{BoundedContext}\...`
- Plugins: `{Vendor}\{PluginName}\...`
- Datei = Klasse, PSR-4 strikt
- Englische Bezeichner im Code, deutsche Texte in der Produkt-UI
- Keine Geschäftslogik in Controllern

## 8.3 Datenbankzugriff

- Ein Query-Builder oder explizite Repositories, kein SQL in Views.
- Jede inhaltliche Query filtert `site_id`.
- Transaktionen um Publish (Revision → Snapshot → Page-Update → Cache-Event).
- JSON-Updates an Dokumenten ersetzen das ganze Dokument, kein unsicheres Patchen tiefer Keys ohne Schema.

## 8.4 Qualitätssicherung

| Werkzeug | Minimum |
|---|---|
| PHPUnit | Unit für Domain, Integration für Repositories und Loader |
| PHPStan | Level 8, Baseline nur für Altlasten (am Anfang: keine Baseline) |
| PHP CS Fixer oder Pint | CI muss formatieren bzw. prüfen |
| Composer Audit | bei jedem CI-Lauf |

Tests, die Policies umgehen, sind ungültig. Jedes neues Repository braucht mindestens einen Test, der `site_id`-Scoping prüft.

## 8.5 Git

- `main` ist immer deploybar.
- Feature-Branches, kleine PRs.
- Commit-Messages auf Deutsch oder Englisch, aber einheitlich im Team festlegen (Empfehlung: Englisch, Imperativ: `Add plugin manifest validation`).
- Keine `vendor/`, `.env`, Uploads, generierte Assets ohne Hash-Pipeline.

## 8.6 Abhängigkeiten

Neue Composer-Pakete brauchen:

- Lizenzkompatibilität
- aktive Wartung
- PSR-Anschluss wo möglich
- Begründung im PR

Der Kern zieht Symfony-Komponenten einzeln, nicht `symfony/symfony`.

## 8.7 Frontend des Builders

- Getrennt vom öffentlichen Theme gebündelt.
- TypeScript, striktes Mode.
- Block-Editor spricht nur die Admin-API, kein Direktzugriff auf MariaDB.
- Accessibility: Tastaturbedienung für Block-Auswahl ist Pflicht ab Phase 2, Drag-and-Drop ist Ergänzung.
  - Builder: Pfeiltasten wählen Blöcke, Alt+Pfeil verschiebt Geschwister, Entf entfernt; ↑/↓-Buttons im Canvas.

## 8.8 Dokumentation

Verträge (Manifest, Events, Tabellenkern) werden in `docs/` geändert **im selben PR** wie der Code.

## 8.9 Referenzumgebung

- PHP 8.4 FPM bzw. XAMPP-Apache, Extension `intl` aktiv
- MariaDB 11.4 LTS oder 10.11 LTS
- XAMPP ist die lokale Referenz: Document Root = Projektordner (`htdocs/nexis/`), Front Controller `index.php`, Schutz über `.htaccess`
- Production: Apache oder nginx mit derselben Root-Politik, PHP 8.4 FPM, eigener MariaDB-User
- `memory_limit` mind. 256M für Admin/Builder
