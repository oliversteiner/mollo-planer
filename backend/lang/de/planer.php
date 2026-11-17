<?php

return [
    'import' => [
        'invalid' => 'Die Datei ist ungültig: :message',
        'revision' => 'Die Datei hat die Revision :file, das Projekt ist bei :project. Exportiere es zuerst neu.',
        'unknown_id' => ':id gehört nicht zu diesem Projekt oder liegt im Papierkorb.',
        'denied' => 'Dafür fehlt dir das Recht: :what.',
        'what' => [
            'create_project' => 'Projekte anlegen',
            'update_project' => 'das Projekt ändern',
            'create_milestone' => 'einen Milestone anlegen',
            'create_todo' => 'ein ToDo anlegen',
            'create_task' => 'einen Task anlegen',
            'update' => 'ändern',
            'move' => 'den Status ändern',
            'assign' => 'zuweisen',
            'delete' => 'löschen',
            'members' => 'Mitglieder ändern',
        ],
        'person_unknown' => 'Zu :email gibt es kein freigegebenes Konto. Ein Import legt keine Konten an.',
        'role_unknown' => 'Die Projektrolle „:role“ gibt es nicht.',
        'self_removal' => 'Die Datei würde dich selbst aus dem Projekt entfernen. Nimm dich in die Mitglieder auf oder importiere im Modus „partial“.',
        'description' => ':problem',
        'assignee_not_member' => ':email ist kein Mitglied des Projekts.',
        'nothing_to_delete' => 'Nichts zu löschen.',
    ],
];
