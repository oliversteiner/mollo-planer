<?php

return [
    'templates' => [
        'software-development' => ['name' => 'Software development', 'description' => 'Template for software and product development: from research to the first release.'],
        'event' => ['name' => 'Event', 'description' => 'Template for events and conferences: planning, speakers and announcement.'],
        'podcast' => ['name' => 'Podcast', 'description' => 'Template for a podcast production: from the topic idea to publishing.'],
    ],
    'categories' => [
        'phase' => 'Phase',
        'type' => 'Type',
        'status' => 'Status',
        'trade' => 'Trade',
        'pipeline' => 'Pipeline',
        'promotion' => 'Promotion',
    ],
    'copy_of' => 'Copy of :name',
    'not_in_set' => 'This label is not one of the labels of the project.',
    'too_many' => 'At most :max labels.',
    'at_least_one' => 'A template needs at least one label.',
    'duplicate_name' => 'Each name may appear only once per language.',
    'key_unknown' => 'This label does not exist.',
    'key_twice' => 'This label is in the list twice.',
    'name_invalid' => 'A name has 1 to 40 characters, in German or English.',
    'language_twice' => 'Each language may appear only once per label.',
    'name_missing' => 'The name in :language is missing.',
    'template_name_taken' => 'A template with this name exists already.',
    'template_unknown' => 'This template does not exist.',
    'template_invalid' => 'The template is not valid at this point (:path): :problem',
];
