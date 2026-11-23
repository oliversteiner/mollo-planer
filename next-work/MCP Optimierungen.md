Statt neue Tools zu erfinden, bleiben alle Namen erhalten – der Fokus liegt auf schlanken Parametern, gezielten Filtern und reduzierten Rückgaben:

list_projects & list_templates & list_users: Nur kompakte Listenfelder zurückgeben (id, name, role/abilities bzw. key/locale bzw. id, name, email) statt voller verschachtelter Objekte. Bei list_users nur bei Bedarf aufrufen (z. B. wenn Mitglieder/Zuweisungen gefragt sind).

export_plan: Bei größeren Projekten immer zuerst Filter nutzen (version, milestone_ids, task_status, include_released: false), statt sofort den kompletten Planer-v2-Text zu ziehen. Das Ergebnis ist dann ein Ausschnitt (excerpt: true), der nur mit mode: "partial" zurückgeschrieben werden darf.

import_plan:

Immer zuerst mit dry_run: true prüfen.

In der Tool-Antwort nur kompakte Zusammenfassungen (ok, dry_run, project_id, changes als kompakte Liste, errors, id_map) ausgeben – den kompletten document-String nicht erneut an den Agenten zurückgeben, wenn er ihn bereits lokal/im Kontext hat.

update_tasks: Für Status (idea, planned, open, in_progress, review, done), commit oder assignee nutzen – erst dry_run: true mit base_revision aus export_plan, danach scharf schalten. Rückgabe auf betroffene Task-IDs, neue Werte und Fehler beschränken.

list_comments & add_comment: list_comments mit einem sinnvollen Standard-Limit (z. B. letzte 10–20 Kommentare) und kompaktem Textauszug abrufen; Kommentare gehören nicht in die Planer-Datei.

list_ideas & create_idea & get_project_ai_context: Nur bei explizitem Bezug auf Ideen oder Projekt-Hintergrundwissen aufrufen (get_project_ai_context respektiert sharedWithTools und kann bis zu 20.000 Zeichen liefern – bei großen Texten nur bei Bedarf laden).