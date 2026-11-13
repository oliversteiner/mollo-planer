# Format version-<name>.md

Version 1 des Formats. Gilt für die Planung und die Versionshistorie eines Projekts. Eine Datei pro Projekt, im Ordner `docs/`, Name `version-<name>.md` (z. B. `version-fcadb.md`).

Die Datei ist zugleich lesbar für Menschen und für das Werkzeug `mollo-planer`. Der Validator (`mollo-planer validate`) ist die Prüfung. Eine Regel, die hier steht, gilt auch dann, wenn der Validator sie noch nicht prüft; dann ist das ein Fehler im Werkzeug.

## 1. Aufbau

| Element | Schreibweise | Bedeutung |
|---|---|---|
| Milestone | `## vM.m Titel` | Eine Version. Nur Major und Minor, z. B. `v1.4`. |
| ToDo | `### Titel` | Arbeitskontext innerhalb eines Milestones. |
| Task | `- [x] [art] Titel` oder `- [ ] [art] Titel` | Eine Arbeitseinheit. `[x]` erledigt, `[ ]` offen. |
| Commit | `  Commit <Hash>` direkt unter einem erledigten Task | Verweis auf den Commit (7 bis 40 Hex-Zeichen). Alternativ `  Ohne Commit` für Altbestand. |
| Beschreibung | eingerückte Zeilen (zwei Leerzeichen) unter einem Task | Text des Tasks. |
| Status | `Status: geplant` oder `Status: veröffentlicht` direkt unter der Milestone-Überschrift | Ohne Zeile gilt `geplant`. Steuert die Freigabe (Regel 8). |
| Beschreibung eines Milestones oder ToDos | einfache Zeile direkt unter der Überschrift | Text des Milestones oder ToDos. |
| Einleitung und andere Abschnitte | `## Titel`, das keine Version ist | Wird übersprungen. Ausnahme: `## Aufbau` ist still erlaubt, andere geben einen Hinweis. |

Erlaubte Arten: `feature`, `bug`, `planning`, `research`, `design`, `blocked`.

Der Status von Milestones und ToDos wird nicht geschrieben. Er entsteht aus den Tasks.

## 2. Regeln

Fehler brechen den Import ab. Hinweise nicht.

1. **Versionen:** Nur `vM.m`, ganze Zahlen. Eine Patch-Stufe (`vM.m.p`) ist ein Fehler. Jede Version ist größer als die vorherige.
2. **Titel:** Milestone, ToDo und Task höchstens 160 Zeichen. Task mindestens 2 Zeichen.
3. **Struktur:** Ein Task steht in einem ToDo, ein ToDo in einem Milestone. Ein Milestone hat mindestens ein ToDo, ein ToDo mindestens einen Task.
4. **Art:** Nur die oben genannten Arten.
5. **Doppelte Titel:** Innerhalb eines ToDos ist ein Task-Titel nur einmal erlaubt.
6. **Commit:** Ein erledigter Task (`[x]`) braucht unmittelbar darunter `  Commit <Hash>`. Fehlt er, ist das ein Fehler. Die Zeile muss genau dieser Form entsprechen. Ausnahme für Altbestand: `  Ohne Commit` direkt unter einem erledigten Task ist gültig (Hash unbekannt oder nicht mehr nötig). Unter einem offenen Task ist es ein Fehler.
7. **Lesbare Zeilen:** Jede Zeile, die mit `- [` beginnt, muss ein gültiger Task sein.
8. **Freigabe:** Eine Version mit `Status: veröffentlicht` darf keinen offenen Task haben (Fehler). Eine geplante Version, deren Tasks alle erledigt sind, bekommt einen Hinweis, dass der Status gesetzt werden kann. Der Status steht nur direkt unter der Überschrift, und nur `geplant` oder `veröffentlicht` sind erlaubt.

## 3. Zusammenspiel mit der Versionshistorie

- Eine Version wird erst veröffentlicht, wenn alle ihre Tasks erledigt sind (Regel 8). Das folgt aus `plan-versionen`.
- Die Versionsnummer ist `M.m`. Die aktuelle Version ist die höchste veröffentlichte.
- **Git-Tag:** Bei der Freigabe einer Version wird auf den Release-Commit (auf `main`) der Tag `vM.m.0` gesetzt, z. B. `v1.4.0` für die Version `1.4`. Die Anzeige in der App und in der Doku bleibt `M.m`. Der Patch-Teil ist immer `0`, weil es keine Patch-Stufe gibt. Ein GitHub-Release nutzt denselben Tag.
- Ein Milestone auf GitHub ist optional. Wenn er angelegt wird, heißt er `vM.m` (ohne `.0`), die Doku bleibt die Quelle.
- Das Datum einer Version (`released_on`) steht nicht in der Datei, sondern in der Datenbank (siehe Modul `backend`, Phase 3).

## 4. Ausgabe

`mollo-planer json <datei>` schreibt die gelesene Struktur als JSON:

```json
{
  "milestones": [
    {
      "major": 1, "minor": 0, "number": "1.0", "title": "Grundlage", "status": "geplant", "description": null,
      "todos": [
        {
          "title": "Datenmodell", "description": null,
          "tasks": [
            { "title": "Add the data model", "description": null, "done": true, "kind": "feature", "commit": "abc1234" }
          ]
        }
      ]
    }
  ]
}
```

Die Ausgabe ist nur für gültige Dateien.

## 5. Änderungen am Format

Ein neues Format bekommt eine neue Hauptversion dieser Datei (Version 2). Bestehende Dateien bleiben lesbar, solange der Validator die alte Version kennt.
