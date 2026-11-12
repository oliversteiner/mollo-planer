# Connector ausbauen: Status, Ergänzen und Reihenfolge

Ziel: Claude soll einen bestehenden Plan vollständig pflegen können. Das heißt: erledigte Punkte als erledigt anlegen, fehlende Punkte in bestehende Milestones und Todos einfügen, neue Versionen an der richtigen Stelle einordnen und keine Duplikate erzeugen.

Auslöser ist die Pflege von FCAarau DB (Projekt-ID 2) aus `docs/version-club-db.md` im Club-DB-Repo. Dort stehen die Versionen als Milestones, die Abschnitte als Todos und die Punkte als Aufgaben.

## Ausgangslage (Stand 12.11.2026)

Vorhanden:
- `list_projects`, `get_project`, `list_users`, `create_project`, `set_project_member`
- `create_project_plan`: legt Milestones mit Todos und Aufgaben an, alles oder nichts
- `set_task_status`: setzt eine Aufgabe auf open, in_progress, review oder done. Todo und Milestone werden berechnet.

Fehlt:
1. `create_project_plan` gibt nur Zählwerte zurück, keine IDs. Folgeaufrufe sind deshalb nicht möglich, ohne `get_project` zu lesen und zu suchen.
2. Neue Milestones werden immer ans Ende gehängt. Die Reihenfolge der Versionen lässt sich nicht festlegen.
3. Es gibt keinen Weg, Todos oder Aufgaben an einen bestehenden Milestone bzw. Todo anzuhängen. Ein erneuter Aufruf von `create_project_plan` würde den Milestone doppelt anlegen.
4. Aufgaben lassen sich beim Anlegen nicht als erledigt anlegen. Erledigte Punkte müssen als offen angelegt und einzeln nachgezogen werden.
5. Titel, Beschreibung und Fälligkeit eines Milestones oder Todos lassen sich nicht nachträglich ändern.

## Anforderungen

### 1. IDs zurückgeben
`create_project_plan` liefert pro Milestone, Todo und Aufgabe `id` und `title`. Die Antwort sollte so aussehen, dass ein Folgeaufruf ohne Suche möglich ist.

### 2. Reihenfolge festlegen
Entweder:
- `create_project_plan` bekommt optional `position` (0-basiert) je Milestone, oder
- neues Werkzeug `move_milestone(milestone_id, position)`.

Bestehende Milestones bleiben beim Verschieben unverändert, nur die Position ändert sich. Nur Projektrechte MILESTONE_UPDATE.

### 3. Ergänzen in bestehende Milestones
Neues Werkzeug `add_to_plan`:
- Argumente: `project_id`, `milestone_id`, `todos` (gleiche Struktur wie in `create_project_plan`). Ein Todo kann auch an einen bestehenden Todo angehängt werden, wenn `todo_id` angegeben ist; dann nur `tasks`.
- Rechte: TODO_CREATE für Todos, TASK_CREATE für Aufgaben.
- Keine Duplikate: existiert im selben Todo bereits eine Aufgabe mit gleichem Titel, wird der ganze Aufruf mit einem Fehler abgebrochen, der die betroffene Aufgabe nennt. Kein stillschweigendes Überspringen.
- Alles oder nichts, wie `create_project_plan`.

### 4. Erledigt beim Anlegen
Optional `status` je Aufgabe (`open` oder `done`, Standard `open`), in `create_project_plan` und `add_to_plan`. `done` wird intern über denselben Pfad wie `set_task_status` gesetzt, damit die Berechnung von Todo und Milestone läuft. Keine direkte Feldzuweisung. Rechte: TASK_MOVE, sonst Fehler.

### 5. Nachträglich ändern
Neues Werkzeug `update_plan_item(type, id, title?, description?, due_date?)`, mit `type` = `milestone` oder `todo`. Nur die angegebenen Felder werden geändert. Status wird nicht gesetzt, er bleibt berechnet. Rechte: MILESTONE_UPDATE bzw. TODO_UPDATE.

### 6. Unverändert
- Status von Milestone und Todo bleibt berechnet, nie direkt setzbar.
- Keine Löschfunktion im Connector.
- Mails nur mit `notify_assignees`, wie bisher. Statusänderungen senden keine Mails.
- Rechte folgen `ProjectPermissions`. Keine Änderung an den Rechten.
- Oberfläche und GraphQL bleiben unverändert, außer wenn GraphQL-Mutationen für Position oder Anhängen fehlen. Dann nur ergänzen, nicht umbauen.

## Hinweise zum Code

- Vorlagen: `backend/app/Mcp/Tools/CreateProjectPlanTool.php`, `SetTaskStatusTool.php` (neu), `ProjectPlanImporter.php`, `ItemStatusSync.php`, `KanbanOrdering.php`.
- Status-Berechnung nur über `ItemStatusSync`.
- Neue Werkzeuge in `MolloPlanerServer.php` registrieren und in `docs/claude-connector.md` in der Tabelle ergänzen.

## Tests (backend/tests/Feature/Mcp/)

- `create_project_plan` liefert IDs für Milestones, Todos und Aufgaben.
- `position` setzt die Reihenfolge; ohne Angabe wird ans Ende gehängt.
- `add_to_plan` mit `milestone_id` hängt einen Todo an; mit `todo_id` hängt nur Aufgaben an.
- `add_to_plan` mit doppeltem Titel im selben Todo: Fehler, keine Änderung.
- `status: done` bei Anlage: Aufgabe, Todo und Milestone sind danach done, sofern alle Geschwister done sind.
- Ohne Projektrecht: Fehler, keine Änderung.
- `update_plan_item` ändert nur die angegebenen Felder.

## Abnahme auf FCAarau DB (Projekt-ID 2)

Stand im Planer (Auszug):
- Milestones: v1.4 (ID 8), v1.6 (9), v1.7 (10), v1.8 (11), v1.9 (12), v1.10 (13), v1.11 (14), v1.12 (15), v1.13 (16), v1.14 (17), v2.0 (18)
- Aufgaben B-04 bis B-07, B-20, B-16 und B-34 sind erledigt; v1.4 und v1.6 sind damit erledigt.

Prüfen:
1. Aufgabe B-68 „Meldungen einzelnen Personen zuweisen“ als neuer Todo „Meldungen“ in v1.10 (ID 13) anlegen. Kein Duplikat.
2. Die bereits erledigten Punkte in v1.4 (B-01, B-02, B-03, B-24) und v1.6 (B-08) als erledigte Aufgaben in die bestehenden Todos einfügen. Keine Duplikate.
3. Milestones v1.0 bis v1.3 und v1.5 an den Anfang setzen, als erledigte Milestones mit allen Punkten. v1.5 hat 12 Punkte in zwei Todos.
4. Reihenfolge nach Versionsnummer prüfen.

Die Abnahme gilt als bestanden, wenn `get_project` für FCAarau DB die Reihenfolge v1.0 bis v2.0 zeigt, keine Aufgabe doppelt vorkommt und alle erledigten Punkte als erledigt stehen.
