# Fix M4 (F7): Befunde der Abnahme M4

Stand: 2026-11-18, Planung. Geprüft in Chrome an `dev` bis `cc56329` mit frischen Demo-Daten. Rolle: Auftrag für die Umsetzung (Bob). Ein Commit je Punkt, `umsetzung.md` „Stand“ nachführen. Ergebnisse: `docs/abnahme-m4.md`.

## F7.1 Reihenfolge-Abgleich im Import vergleicht Indizes mit IDs (wichtig)

**Befund:** Nach einem Import (Tasks im ToDo umsortiert) meldet ein Trockenlauf mit dem **frischen Export** 2 × `MOVE`, obwohl `tasks.position` genau der Export-Reihenfolge entspricht. Ein echter Import dieser unveränderten Datei zählt dann die Revision hoch; `partial` meldet Verschiebungen von Elementen, die gar nicht in der Datei stehen. Für `pull`/`push` (M5) hieße das: nie „unverändert“.
**Ursache:** `App\Planer\Import::load()`: `$this->todos->groupBy('milestone_id')` und `$this->tasks->groupBy('todo_id')` ohne `preserveKeys`; `$group->keys()` liefert `0, 1, 2 …` statt der IDs. `orderGroup()` vergleicht dann diese Indizes mit IDs (`$before`).
**Umsetzung:** `groupBy(…, preserveKeys: true)` (bzw. `->map(fn ($g) => $g->pluck('id'))`). Tests:
- Export → Trockenlauf mit demselben Text: `changes` leer, auch nach einem vorherigen Import mit Umsortierung, für Milestones, ToDos und Tasks.
- Import ohne Änderungen: Revision unverändert.
- `partial` mit einem Teil der Tasks eines ToDos: keine `MOVE` für die nicht genannten Tasks, solange ihre relative Reihenfolge gleich bleibt.

## F7.2 Trockenlauf zeigt und verbraucht echte IDs

**Befund:** Trockenlauf meldet für `k-new-1` die ID `k-16`, der echte Import vergibt `k-17`. Der Trockenlauf legt offenbar an und rollt zurück; die Auto-Increment-Nummer ist danach verbraucht.
**Umsetzung:** Im Trockenlauf für neue Elemente die ID aus der Datei (`k-new-1`) melden, keine echte ID (auch `idMap` leer). Die verbrauchte Nummer ist hinnehmbar (V1: IDs müssen nicht lückenlos sein), darf aber nirgends angezeigt werden. Test.

## F7.3 Meldungen der Datei-Prüfung auf Deutsch/Englisch

**Befund:** Meldungen aus Schema- und Semantikprüfung sind englisch hinter einem deutschen Präfix: „Die Datei ist ungültig: Maximum string length is 5000, found 5001“, „… ben.mueller@example.ch is not a member of the project“.
**Umsetzung:** `App\Planer\Validator` übersetzt die Fehlerarten (Länge, Pflichtfeld, Muster, Aufzählung, unbekanntes Feld, Mitglied fehlt, Label fehlt, ID doppelt, Release-Regeln) in `lang/{de,en}/planer.php`, Sprache der Anfrage; der Pfad bleibt. Für unbekannte Fälle der Originaltext. Test je Art (DE).

## F7.4 Vorschau des Imports (`PlanerTree`)

**Befund:**
- Ein geänderter Task (Titel geändert) trägt keine Markierung „Geändert“; nur „Neu“ erscheint.
- Gelöschte Elemente erscheinen als „ToDo t-7“ ohne Titel.
- Die Vorschau im Dialog scrollt seitlich (lange Zeilen mit Status, Datum, Zuständig).
**Umsetzung:** `update` und `move` aus `ImportResult.changes` am Element markieren („Geändert“, „Verschoben“, mit Icon und Text); gelöschte mit Titel aus der App (`changes` liefert den Titel mit, oder die Vorschau schlägt ihn im aktuellen Projekt nach); Zeilen im Dialog umbrechen statt seitlich scrollen (Metadaten in eine zweite Zeile). Test der Markierungen (Vitest).

## F7.5 Beschriftung „Sprache der Markdown-Ansicht“

**Befund:** Projekteinstellungen › Planer-Datei: Die zweizeilige Beschriftung überlappt das Auswahlfeld.
**Umsetzung:** kürzere Beschriftung („Sprache“, darunter der Hinweis) oder breiteres Feld; hell/dunkel, DE/EN ansehen.

## F7.6 Task-Menü der Zeitachse

**Befund:** Das neue Task-Menü der Zeitachse hat nur „Nach oben“/„Nach unten“.
**Umsetzung:** wie die Kanban-Karte (F5.3): „Ändern“ (mit `viewerCanUpdate`) und „Löschen“ (mit `viewerCanDelete`, über `useDelete`) ergänzen.

## Ablauf

F7.1 → F7.2 → F7.3 → F7.4 → F7.5 → F7.6. Vorher oder danach `next-work/03` (`DevelopmentHistorySeeder`). Prüfungen, im Browser ansehen, dann anhalten. Für die Nachprüfung danach `php artisan migrate:fresh --seed --seeder=DemoSeeder` (macht Oliver, falls in der Sitzung nicht erlaubt).

## Nachprüfung (2026-11-18, Planung)

F7.1 bis F7.6 in Chrome nachgeprüft: alle ✅; `next-work/03` erledigt. Details in `docs/abnahme-m4.md`.
