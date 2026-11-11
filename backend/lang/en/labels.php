<?php

return [
    'sets' => [
        'software_dev' => ['name' => 'Software development', 'description' => 'Standard workflow for software and product development'],
        'event' => ['name' => 'Event', 'description' => 'Planning for live events, trade fairs and conferences'],
        'podcast' => ['name' => 'Podcast', 'description' => 'Audio production pipeline from the idea to publishing'],
    ],
    'labels' => [
        'research' => 'research',
        'design' => 'design',
        'feature' => 'feature',
        'planning' => 'planning',
        'bug' => 'bug',
        'blocked' => 'blocked',
        'location' => 'location',
        'speakers' => 'speakers',
        'marketing' => 'marketing',
        'confirmed' => 'confirmed',
        'topic_idea' => 'topic idea',
        'guest_booking' => 'guest booking',
        'audio_editing' => 'audio editing',
        'social_media' => 'social media',
    ],
    'categories' => [
        'phase' => 'phase',
        'type' => 'type',
        'status' => 'status',
        'trade' => 'trade',
        'pipeline' => 'pipeline',
        'promotion' => 'promotion',
    ],
    'copy_of' => 'Copy of :name',
    'builtin_readonly' => 'Built-in sets cannot be changed. Duplicate the set to adapt it.',
    'set_in_use' => 'The set is still used by :count project(s). Choose another set there first.',
    'not_in_set' => 'This label does not belong to the label set of the project.',
    'duplicate_name' => 'Each label may appear only once in a set.',
    'label_other_set' => 'This label does not belong to this set.',
];
