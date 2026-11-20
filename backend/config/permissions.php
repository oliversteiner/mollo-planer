<?php

/*
 * Standard rights (docs/rollen-funktionen-rechte.md, §5). This file is the
 * seed for the tables roles, functions, function_rights and role_functions.
 * App\Support\RightsDefaults writes it; App\Support\Rights reads the tables.
 *
 * A right is a value of App\Enums\Right. A function is a named set of rights
 * of one scope. A role is a named set of functions of one scope.
 * 'project_cap' limits the project rights of a system role (null = all, [] =
 * none). projects.manageAny overrides the cap.
 */
return [
    'functions' => [
        'system' => [
            'create-projects' => [
                'names' => ['de' => 'Projekte anlegen', 'en' => 'Create projects'],
                'rights' => ['projects.create'],
            ],
            'all-projects' => [
                'names' => ['de' => 'Alle Projekte', 'en' => 'All projects'],
                'rights' => ['projects.viewAll', 'projects.manageAny'],
            ],
            'users-and-roles' => [
                'names' => ['de' => 'Benutzer und Rollen', 'en' => 'Users and roles'],
                'rights' => ['users.manage', 'roles.manage'],
            ],
            'templates' => [
                'names' => ['de' => 'Vorlagen', 'en' => 'Templates'],
                'rights' => ['templates.manage'],
            ],
            'all-templates' => [
                'names' => ['de' => 'Alle Vorlagen', 'en' => 'All templates'],
                'rights' => ['templates.manageAny'],
            ],
            'ai-and-ideas' => [
                'names' => ['de' => 'KI und Ideen', 'en' => 'AI and ideas'],
                'rights' => ['assistant.use', 'ideas.create'],
            ],
            'all-ideas' => [
                'names' => ['de' => 'Alle Ideen', 'en' => 'All ideas'],
                'rights' => ['ideas.viewAny'],
            ],
            'operations' => [
                'names' => ['de' => 'Betrieb', 'en' => 'Operations'],
                'rights' => ['settings.manage', 'database.view'],
            ],
            'status' => [
                'names' => ['de' => 'Status', 'en' => 'Status'],
                'rights' => ['system.view'],
            ],
        ],

        'project' => [
            'plan' => [
                'names' => ['de' => 'Planen', 'en' => 'Plan'],
                'rights' => ['milestone.create', 'milestone.update', 'todo.create', 'todo.update'],
            ],
            'contribute' => [
                'names' => ['de' => 'Mitarbeiten', 'en' => 'Contribute'],
                'rights' => ['task.create', 'task.updateOwn', 'task.assignSelf', 'task.move', 'comment.create', 'planer.export', 'document.create', 'document.update'],
            ],
            'manage-tasks' => [
                'names' => ['de' => 'Aufgaben verwalten', 'en' => 'Manage tasks'],
                'rights' => ['task.update', 'task.assign', 'task.delete', 'milestone.delete', 'todo.delete', 'comment.moderate', 'trash.restore'],
            ],
            'manage-project' => [
                'names' => ['de' => 'Projekt verwalten', 'en' => 'Manage project'],
                'rights' => ['project.update', 'project.archive', 'members.manage', 'aiContext.update', 'planer.import', 'document.manageAny'],
            ],
            'own-project' => [
                'names' => ['de' => 'Projekt besitzen', 'en' => 'Own project'],
                'rights' => ['project.delete', 'owner.manage', 'trash.purge'],
            ],
            'agent-work' => [
                'names' => ['de' => 'Plan und Aufgaben pflegen (Agent)', 'en' => 'Keep plan and tasks (agent)'],
                'rights' => ['planer.import', 'task.update', 'task.assign'],
            ],
        ],
    ],

    'roles' => [
        'system' => [
            'admin' => [
                'names' => ['de' => 'Admin', 'en' => 'Admin'],
                'functions' => ['create-projects', 'all-projects', 'users-and-roles', 'templates', 'all-templates', 'ai-and-ideas', 'all-ideas', 'operations', 'status'],
                'project_cap' => null,
            ],
            'projektleitung' => [
                'names' => ['de' => 'Projektleitung', 'en' => 'Project lead'],
                'functions' => ['create-projects', 'templates', 'ai-and-ideas', 'status'],
                'project_cap' => null,
            ],
            'koordination' => [
                'names' => ['de' => 'Koordination', 'en' => 'Coordination'],
                'functions' => ['create-projects', 'ai-and-ideas', 'status'],
                'project_cap' => null,
            ],
            'mitglied' => [
                'names' => ['de' => 'Mitglied', 'en' => 'Member'],
                'functions' => ['ai-and-ideas', 'status'],
                'project_cap' => null,
            ],
            'lesend' => [
                'names' => ['de' => 'Lesend', 'en' => 'Read only'],
                'functions' => ['status'],
                'project_cap' => [],
            ],
        ],

        'project' => [
            'owner' => [
                'names' => ['de' => 'Owner', 'en' => 'Owner'],
                'functions' => ['plan', 'contribute', 'manage-tasks', 'manage-project', 'own-project'],
            ],
            'manager' => [
                'names' => ['de' => 'Manager', 'en' => 'Manager'],
                'functions' => ['plan', 'contribute', 'manage-tasks', 'manage-project'],
            ],
            'member' => [
                'names' => ['de' => 'Mitglied', 'en' => 'Member'],
                'functions' => ['plan', 'contribute'],
            ],
            'guest' => [
                'names' => ['de' => 'Gast', 'en' => 'Guest'],
                'functions' => [],
            ],
            'agent' => [
                'names' => ['de' => 'Agent', 'en' => 'Agent'],
                'functions' => ['plan', 'contribute', 'agent-work'],
            ],
        ],
    ],
];
