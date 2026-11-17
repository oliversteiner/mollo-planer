<?php

return [
    'archived' => 'Das Projekt ist archiviert und nur lesbar. Hole es zuerst zurück.',
    'only_holder' => ':name ist in :projects die einzige Person mit „:right“. Vergib dort zuerst die Rolle an jemand anderen.',
    'list_and' => ' und ',
    'last_owner' => 'Das Projekt braucht mindestens einen Owner. Ernenne zuerst eine andere Person zum Owner.',
    'assignee_not_member' => 'Tasks können nur Mitgliedern des Projekts zugewiesen werden.',
    'todo_other_project' => 'Ein Task kann nur in ein ToDo desselben Projekts verschoben werden.',
    'milestone_other_project' => 'Ein ToDo kann nur in einen Milestone desselben Projekts verschoben werden.',
    'plan' => [
        'not_configured' => 'Der KI-Assistent ist noch nicht eingerichtet. Im Backend fehlt ANTHROPIC_API_KEY in der .env.',
        'invalid_key' => 'Claude lehnt den API-Schlüssel ab. Prüfe ANTHROPIC_API_KEY in der .env des Backends (beginnt mit sk-ant-).',
        'unavailable' => 'Claude ist gerade nicht erreichbar. Bitte versuche es gleich nochmals.',
        'refused' => 'Claude hat diese Anfrage abgelehnt. Bitte formuliere sie anders.',
        'too_large' => 'Der Plan wurde zu gross. Bitte beschränke ihn, zum Beispiel auf weniger Milestones.',
        'milestone' => 'Milestone „:subject“: :detail',
        'todo' => 'ToDo „:subject“: :detail',
        'task' => 'Task „:subject“: :detail',
        'not_a_member' => 'Task „:subject“: :detail ist nicht Mitglied des Projekts.',
        'unknown_person' => 'Zu :subject gibt es kein Konto. Ein Admin muss die Person zuerst einladen (Verwaltung → Benutzer).',
        'duplicate_task' => 'Die Aufgabe „:subject“ gibt es in „:detail“ schon. Es wurde nichts angelegt.',
        'duplicate_todo' => 'Das ToDo „:subject“ gibt es im Milestone „:detail“ schon. Es wurde nichts angelegt.',
    ],
    'member_twice' => 'Diese Person steht schon in der Liste.',
    'member_unknown' => 'Diese Person gibt es nicht oder sie ist noch nicht freigegeben.',
    'member_role_refused' => 'Diese Rolle darfst du hier nicht vergeben.',
];
