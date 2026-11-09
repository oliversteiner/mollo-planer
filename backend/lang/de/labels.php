<?php

return [
    'sets' => [
        'software_dev' => ['name' => 'Softwareentwicklung', 'description' => 'Standard-Workflow für Software- und Produktentwicklung'],
        'event' => ['name' => 'Event', 'description' => 'Planung für Live-Events, Messen und Konferenzen'],
        'podcast' => ['name' => 'Podcast', 'description' => 'Audio-Produktionspipeline von der Idee bis zur Veröffentlichung'],
    ],
    'labels' => [
        'research' => 'Recherche',
        'design' => 'Design',
        'feature' => 'Feature',
        'bug' => 'Fehler',
        'blocked' => 'Blockiert',
        'location' => 'Location',
        'speakers' => 'Speaker',
        'marketing' => 'Marketing',
        'confirmed' => 'Bestätigt',
        'topic_idea' => 'Themenidee',
        'guest_booking' => 'Gästebuchung',
        'audio_editing' => 'Audioschnitt',
        'social_media' => 'Social Media',
    ],
    'categories' => [
        'phase' => 'Phase',
        'type' => 'Typ',
        'status' => 'Status',
        'trade' => 'Gewerk',
        'pipeline' => 'Pipeline',
        'promotion' => 'Promotion',
    ],
    'copy_of' => 'Kopie von :name',
    'builtin_readonly' => 'Mitgelieferte Sets lassen sich nicht ändern. Dupliziere das Set, um es anzupassen.',
    'set_in_use' => 'Das Set wird noch von :count Projekt(en) verwendet. Wähle dort zuerst ein anderes Set.',
    'not_in_set' => 'Dieses Label gehört nicht zum Label-Set des Projekts.',
    'duplicate_name' => 'Jedes Label darf im Set nur einmal vorkommen.',
    'label_other_set' => 'Dieses Label gehört nicht zu diesem Set.',
];
