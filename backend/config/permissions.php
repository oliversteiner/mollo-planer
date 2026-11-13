<?php

/*
 * Standard rights (docs/rollen-funktionen-rechte.md, §5). This file is the
 * seed for the tables roles, functions, function_rights and role_functions.
 * App\Support\RightsDefaults writes it; App\Support\Rights reads the tables.
 *
 * A right is a value of ProjectAbility or SystemAbility. A function is a named
 * set of rights of one scope. A role is a named set of functions of one scope.
 * 'project_cap' limits the project rights of a system role (null = all, [] =
 * none). Projects.manageAny overrides the cap.
 */
return [
    'functions' => [
        'system' => [
            'projekte-anlegen' => [
                'names' => ['de' => 'Projekte anlegen', 'en' => 'Create projects'],
                'rights' => ['projects.create'],
            ],
            'alle-projekte' => [
                'names' => ['de' => 'Alle Projekte', 'en' => 'All projects'],
                'rights' => ['projects.viewAll', 'projects.manageAny'],
            ],
            'benutzer' => [
                'names' => ['de' => 'Benutzer verwalten', 'en' => 'Manage users'],
                'rights' => ['users.manage'],
            ],
            'labelsets' => [
                'names' => ['de' => 'Labelsets verwalten', 'en' => 'Manage label sets'],
                'rights' => ['labelSets.manage'],
            ],
            'labelsets-alle' => [
                'names' => ['de' => 'Alle Labelsets verwalten', 'en' => 'Manage all label sets'],
                'rights' => ['labelSets.manageAny'],
            ],
        ],

        'project' => [
            'planen' => [
                'names' => ['de' => 'Planen', 'en' => 'Plan'],
                'rights' => ['milestone.create', 'milestone.update', 'todo.create', 'todo.update'],
            ],
            'aufgaben-bewegen' => [
                'names' => ['de' => 'Aufgaben verschieben', 'en' => 'Move tasks'],
                'rights' => ['task.move', 'comment.create'],
            ],
            'aufgaben-bearbeiten' => [
                'names' => ['de' => 'Aufgaben bearbeiten', 'en' => 'Edit tasks'],
                'rights' => ['task.create', 'task.update', 'task.assign'],
            ],
            'loeschen' => [
                'names' => ['de' => 'Löschen', 'en' => 'Delete'],
                'rights' => ['milestone.delete', 'todo.delete', 'task.delete', 'comment.moderate'],
            ],
            'projekt-verwalten' => [
                'names' => ['de' => 'Projekt verwalten', 'en' => 'Manage project'],
                'rights' => ['project.update', 'members.manage'],
            ],
            'projekt-besitzen' => [
                'names' => ['de' => 'Projekt besitzen', 'en' => 'Own project'],
                'rights' => ['project.delete'],
            ],
        ],
    ],

    'roles' => [
        'system' => [
            'admin' => [
                'names' => ['de' => 'Admin', 'en' => 'Admin'],
                'functions' => ['projekte-anlegen', 'alle-projekte', 'benutzer', 'labelsets', 'labelsets-alle'],
                'project_cap' => null,
            ],
            'projektleitung' => [
                'names' => ['de' => 'Projektleitung', 'en' => 'Project lead'],
                'functions' => ['projekte-anlegen', 'labelsets'],
                'project_cap' => null,
            ],
            'koordination' => [
                'names' => ['de' => 'Koordination', 'en' => 'Coordination'],
                'functions' => ['projekte-anlegen'],
                'project_cap' => null,
            ],
            'mitglied' => [
                'names' => ['de' => 'Mitglied', 'en' => 'Member'],
                'functions' => [],
                'project_cap' => null,
            ],
            'lesend' => [
                'names' => ['de' => 'Lesend', 'en' => 'Read only'],
                'functions' => [],
                'project_cap' => [],
            ],
        ],

        'project' => [
            'owner' => [
                'names' => ['de' => 'Owner', 'en' => 'Owner'],
                'functions' => ['planen', 'aufgaben-bewegen', 'aufgaben-bearbeiten', 'loeschen', 'projekt-verwalten', 'projekt-besitzen'],
            ],
            'manager' => [
                'names' => ['de' => 'Manager', 'en' => 'Manager'],
                'functions' => ['planen', 'aufgaben-bewegen', 'aufgaben-bearbeiten', 'loeschen', 'projekt-verwalten'],
            ],
            'member' => [
                'names' => ['de' => 'Mitglied', 'en' => 'Member'],
                'functions' => ['planen', 'aufgaben-bewegen'],
            ],
        ],
    ],
];
