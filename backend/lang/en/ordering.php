<?php

return [
    'revision' => 'The plan has changed in the meantime (revision :project, your base was :file). Reload it and try again.',
    'siblings' => 'ids must contain exactly all siblings. Missing: :missing. Foreign: :foreign.',
    'forbidden' => 'You may not change the order here.',
    'parent_missing' => 'Give parent_id (m-… for todos, t-… for tasks) or leave it out for the milestones.',
    'condition' => [
        'no_right' => 'You may not edit the milestones of this project.',
        'archived' => 'The project is archived.',
        'too_few_versions' => 'At least two milestones need a version.',
        'invalid_version' => 'These versions are invalid (only numbers with dots such as 1.4 or 2.0.1 are allowed): :list',
        'duplicate_version' => 'These versions appear twice: :list',
        'already_ordered' => 'Already ordered by version.',
    ],
];
