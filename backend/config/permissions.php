<?php

/*
 * Standard rights (docs/rollen-funktionen-rechte.md, §5). Which role has which
 * right is read only from here by App\Support\Rights; the policies never
 * compare roles. Values are the enum values of ProjectAbility and SystemAbility.
 *
 * 'admin' has every system right. Projects.manageAny gives every project right.
 */
return [
    'system' => [
        'admin' => ['*'],
        'projektleitung' => ['projects.create', 'labelSets.manage'],
        'koordination' => ['projects.create'],
        'mitglied' => [],
        'lesend' => [],
    ],

    'project' => [
        'owner' => [
            'project.update',
            'project.delete',
            'members.manage',
            'milestone.create',
            'milestone.update',
            'milestone.delete',
            'todo.create',
            'todo.update',
            'todo.delete',
            'task.create',
            'task.update',
            'task.assign',
            'task.move',
            'task.delete',
            'comment.create',
            'comment.moderate',
        ],
        'manager' => [
            'project.update',
            'members.manage',
            'milestone.create',
            'milestone.update',
            'milestone.delete',
            'todo.create',
            'todo.update',
            'todo.delete',
            'task.create',
            'task.update',
            'task.assign',
            'task.move',
            'task.delete',
            'comment.create',
            'comment.moderate',
        ],
        'member' => [
            'milestone.create',
            'milestone.update',
            'todo.create',
            'todo.update',
            'task.move',
            'comment.create',
        ],
    ],
];
