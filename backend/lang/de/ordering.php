<?php

return [
    'revision' => 'Der Plan wurde inzwischen geändert (Revision :project, deine Basis war :file). Lade ihn neu und versuche es noch einmal.',
    'siblings' => 'ids muss genau alle Geschwister enthalten. Fehlend: :missing. Fremd: :foreign.',
    'forbidden' => 'Du darfst hier die Reihenfolge nicht ändern.',
    'parent_missing' => 'Gib parent_id an (m-… für ToDos, t-… für Tasks) oder lass sie weg für die Milestones.',
    'condition' => [
        'no_right' => 'Du darfst die Milestones dieses Projekts nicht bearbeiten.',
        'archived' => 'Das Projekt ist archiviert.',
        'too_few_versions' => 'Mindestens zwei Milestones brauchen eine Version.',
        'invalid_version' => 'Diese Versionen sind ungültig (erlaubt sind nur Zahlen mit Punkten wie 1.4 oder 2.0.1): :list',
        'duplicate_version' => 'Diese Versionen kommen doppelt vor: :list',
        'already_ordered' => 'Bereits nach Version geordnet.',
    ],
];
