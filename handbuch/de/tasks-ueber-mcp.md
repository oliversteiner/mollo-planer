---
id: tasks-ueber-mcp
title: Tasks über MCP ändern
area: agents
scope: app
right: task.update
pages:
tools: list_projects, list_tasks, update_tasks, export_plan, import_plan, add_comment
commands:
since: 1.6
---

## Wozu

Ein Agent liest und ändert Tasks über den MCP-Server, ohne den ganzen Plan zu laden. So bleibt jede Änderung klein, und der Stand im Planer stimmt mit der Arbeit überein.

## Gut zu wissen

- Der Agent handelt als sein eigenes Konto, mit genau dessen Rechten. Was er im Projekt nicht darf, verweigert der Server.
- Status von ToDo und Milestone leitet der Planer aus den Tasks ab. Ein Agent setzt sie nie selbst.
- Welche Status ein Agent setzen darf, bestimmt seine Rolle im Projekt. Eine Person im Projekt kann zusätzlich festlegen, wer einen Task als erledigt markiert.

## Für Agenten

- Einstieg: `list_projects` liefert die Projekte, deine Rolle und die Revision des Plans.
- Finden: `list_tasks` mit Filtern (`assignee: "me"`, `status`, Text), klein halten mit `limit`.
- Status, Commit, Zuweisung: `update_tasks` direkt, ohne Export und ohne Trockenlauf. `base_revision` nur, wenn nichts dazwischen geändert sein darf.
- Neue Tasks oder Titel ändern: `export_plan` gefiltert auf einen Milestone, ändern, `import_plan` mit `mode: "partial"`, zuerst mit `dry_run: true`.
- Berichte und Fragen: `add_comment` am Task.
- Nicht über MCP: Tasks endgültig löschen, Rechte oder Rollen ändern.

## Beispielprompts

- Zeige mir mit `list_tasks` meine Tasks im Projekt [Projekt], die «In Arbeit» oder «Review» sind.
- Setze mit `update_tasks` den Task [k-123] auf «Review» und trage den Commit [Hash] ein. Schreibe danach mit `add_comment` einen kurzen Bericht an den Task.
- Lege im Milestone [Milestone] des Projekts [Projekt] einen Task «[Titel]» an: exportiere nur diesen Milestone, ergänze den Task und importiere mit `mode: "partial"`, zuerst als Trockenlauf.
