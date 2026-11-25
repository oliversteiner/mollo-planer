---
id: task-anlegen
title: Task anlegen und bearbeiten
area: plan
scope: app
right: task.create
pages: /projects/[id]/kanban
tools: list_tasks, update_tasks, import_plan
commands: kanban-task-create
since: 1.6
---

## Wozu

Ein Task ist ein einzelner Arbeitsschritt in einem ToDo. Auf dem Board siehst du, was offen ist, was läuft und was erledigt ist.

## So geht's

1. Öffne das Projekt und wähle den Reiter **{{ui:project.tabs.tasks}}**.

   {{bild:board|Das Board eines Projekts mit Spalten für jeden Status}}

2. Wähle oben rechts **{{ui:task.create}}**. Es öffnet sich ein Formular.
3. Wähle unter **{{ui:task.todo}}** das ToDo aus, zu dem der Task gehört.
4. Gib im Feld **{{ui:item.fields.title}}** einen Titel ein.

   {{bild:formular|Das Formular für einen neuen Task mit ToDo und Titel}}

5. Fülle bei Bedarf weitere Felder aus, zum Beispiel **{{ui:task.assignee}}** oder **{{ui:item.fields.dueDate}}**.
6. Wähle **{{ui:common.save}}**. Das Formular schliesst sich, und der Task steht in der Spalte **{{ui:status.OPEN}}**.
7. Um den Task zu ändern, wähle auf seiner Karte die Taste «…» und dann **{{ui:common.edit}}**.
8. Um den Status zu ändern, wähle auf der Karte die Taste «…» und dann den Eintrag **Verschieben nach** mit der gewünschten Spalte. Alternativ ziehst du die Karte mit gedrückter Maustaste in eine andere Spalte.

## Gut zu wissen

- Ohne ToDo gibt es keinen Task. Fehlt **{{ui:task.create}}** oder ist er grau, lege zuerst ein ToDo an.
- In einem archivierten Projekt kannst du keine Tasks anlegen.
- Ob du Tasks anlegen oder ändern darfst, hängt von deiner Rolle im Projekt ab.
- In der ruhigen Ansicht findest du **{{ui:task.create}}** in der Befehlspalette.

## Für Agenten

Neue Tasks legt ein Agent mit `import_plan` an (Ausschnitt des Plans mit `mode: "partial"`, zuerst `dry_run: true`). Status, Commit und Zuweisung ändert er direkt mit `update_tasks`. Mit `list_tasks` findet er Tasks nach Status, Zuweisung oder Text. Siehe das Kapitel «Tasks über MCP ändern».

## Beispielprompts

- Lege im Projekt [Projekt] im ToDo [ToDo] einen Task «[Titel]» an und weise ihn [Person] zu. Prüfe das Ergebnis zuerst mit einem Trockenlauf.
- Zeige mir alle meine offenen Tasks im Projekt [Projekt], sortiert nach Fälligkeit.
- Setze den Task [k-123] auf «In Arbeit».
