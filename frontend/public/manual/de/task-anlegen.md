# Task anlegen und bearbeiten

- Recht: task.create
- Werkzeuge: list_tasks, update_tasks, import_plan
- Seiten der App: /projects/[id]/kanban

## Wozu

Ein Task ist ein einzelner Arbeitsschritt in einem ToDo. Auf dem Board siehst du, was offen ist, was läuft und was erledigt ist.

## Gut zu wissen

- Ohne ToDo gibt es keinen Task. Fehlt **Task anlegen** oder ist er grau, lege zuerst ein ToDo an.
- In einem archivierten Projekt kannst du keine Tasks anlegen.
- Ob du Tasks anlegen oder ändern darfst, hängt von deiner Rolle im Projekt ab.
- In der ruhigen Ansicht findest du **Task anlegen** in der Befehlspalette.

## Für Agenten

Neue Tasks legt ein Agent mit `import_plan` an (Ausschnitt des Plans mit `mode: "partial"`, zuerst `dry_run: true`). Status, Commit und Zuweisung ändert er direkt mit `update_tasks`. Mit `list_tasks` findet er Tasks nach Status, Zuweisung oder Text. Siehe das Kapitel «Tasks über MCP ändern».

## Beispielprompts

- Lege im Projekt \[Projekt\] im ToDo \[ToDo\] einen Task «\[Titel\]» an und weise ihn \[Person\] zu. Prüfe das Ergebnis zuerst mit einem Trockenlauf.
- Zeige mir alle meine offenen Tasks im Projekt \[Projekt\], sortiert nach Fälligkeit.
- Setze den Task \[k-123\] auf «In Arbeit».
