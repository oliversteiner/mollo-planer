<?php

return [
    'last_owner' => 'The project needs at least one owner. Make someone else an owner first.',
    'assignee_not_member' => 'Tasks can only be assigned to members of the project.',
    'plan' => [
        'not_configured' => 'The AI assistant is not set up yet. The backend .env lacks ANTHROPIC_API_KEY.',
        'invalid_key' => 'Claude refuses the API key. Check ANTHROPIC_API_KEY in the backend .env (it starts with sk-ant-).',
        'unavailable' => 'Claude cannot be reached right now. Please try again in a moment.',
        'refused' => 'Claude declined this request. Please phrase it differently.',
        'too_large' => 'The plan got too large. Please narrow it down, for example to fewer milestones.',
        'milestone' => 'Milestone “:subject”: :detail',
        'todo' => 'Todo “:subject”: :detail',
        'task' => 'Task “:subject”: :detail',
        'not_a_member' => 'Task “:subject”: :detail is not a member of the project.',
        'unknown_person' => 'There is no account for :subject. An admin must invite them first (Administration → Users).',
    ],
];
