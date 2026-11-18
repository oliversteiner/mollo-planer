<?php

return [
    'not_configured' => 'Der KI-Assistent ist noch nicht eingerichtet. Im Backend fehlt ANTHROPIC_API_KEY in der .env.',
    'invalid_key' => 'Claude lehnt den API-Schlüssel ab. Prüfe ANTHROPIC_API_KEY in der .env des Backends (beginnt mit sk-ant-).',
    'unavailable' => 'Claude ist gerade nicht erreichbar. Bitte versuche es gleich nochmals.',
    'refused' => 'Claude hat diese Anfrage abgelehnt. Bitte formuliere sie anders.',
    'too_large' => 'Der Plan wurde zu gross. Bitte beschränke ihn, zum Beispiel auf weniger Milestones.',
    'limit_hour' => 'Du hast in dieser Stunde :max Entwürfe erstellt. Wieder möglich ab :time.',
    'limit_day' => 'Du hast heute :max Entwürfe erstellt. Wieder möglich ab :time.',
    'show_all' => 'Alle anzeigen',
    'invalid_draft' => 'Der Entwurf war nicht gültig. Kannst du die Anfrage etwas genauer beschreiben?',
    'no_project' => 'Dieses Projekt gibt es nicht, oder du darfst dort nichts ergänzen.',
    'archived' => 'Das Projekt ist archiviert. Hier kann nichts ergänzt werden. Du kannst die Idee in Ideen ablegen.',
    'unknown_person' => 'Zuweisung an :name durch eine berechtigte Person.',
    'unclear_person' => 'Zu «:name» gibt es kein eindeutiges Konto. Die Zuweisung bleibt leer.',
    'only_self' => 'Du darfst Tasks nur dir selbst zuweisen. Die Zuweisung an :name ist leer geblieben, eine berechtigte Person kann sie setzen.',
    'duplicate_todo' => 'Das ToDo «:title» gibt es im Milestone «:milestone» schon.',
    'duplicate_task' => 'Den Task «:title» gibt es im ToDo «:todo» schon.',
    'description_warning' => ':where: :kind (Zeile :line) wurde zu Text.',
    'preview_failed' => 'Die Vorschau meldet Fehler. Prüfe den Entwurf oder frage nach einer Änderung.',
    'idea_unknown' => 'Diese Idee gibt es nicht.',
];
