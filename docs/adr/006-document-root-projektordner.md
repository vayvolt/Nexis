# ADR 006 – Document Root ist der Projektordner

**Status:** angenommen  
**Datum:** 2026-09-07

## Kontext

Die Installation läuft lokal unter XAMPP (`htdocs/nexis/`). Ein separates `public/` als einziges Document Root würde VirtualHost-Änderungen oder Symlinks erzwingen und vom üblichen XAMPP-Verhalten abweichen.

## Entscheidung

Der Document Root ist der **Projektroot**. Einstieg ist `/index.php`. Statische, publizierte Dateien liegen unter `/assets/`. Alle anderen Verzeichnisse sind per Webserver-Regel nicht abrufbar. Medien werden nicht als statische Dateien aus `storage/` ausgeliefert, sondern über den Front Controller.

## Konsequenzen

- `.htaccess` (Apache) bzw. vergleichbare nginx-Locations sind Teil des Kerns, nicht optional.
- Phase-0-Abnahme prüft HTTP 403 auf `src/`, `vendor/`, `.env`.
- Kein `public/`-Ordner in der Repository-Struktur.
- Production darf denselben Root nutzen; ein späteres Umziehen hinter ein `public/` wäre ein Breaking Change und ist nicht vorgesehen.
