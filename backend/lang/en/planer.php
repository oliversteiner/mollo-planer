<?php

return [
    'import' => [
        'invalid' => 'The file is not valid: :message',
        'revision' => 'The file has revision :file, the project is at :project. Export it again first.',
        'unknown_id' => ':id does not belong to this project or is in the trash.',
        'denied' => 'You lack the right: :what.',
        'what' => [
            'create_project' => 'create projects',
            'update_project' => 'change the project',
            'create_milestone' => 'create a milestone',
            'create_todo' => 'create a to-do',
            'create_task' => 'create a task',
            'update' => 'change',
            'move' => 'change the status',
            'assign' => 'assign',
            'delete' => 'delete',
            'members' => 'change members',
        ],
        'person_unknown' => 'There is no approved account for :email. An import does not create accounts.',
        'role_unknown' => 'There is no project role “:role”.',
        'self_removal' => 'The file would remove you from the project. List yourself among the members, or import in “partial” mode.',
        'description' => ':problem',
        'assignee_not_member' => ':email is not a member of the project.',
        'nothing_to_delete' => 'Nothing to delete.',
    ],
];
