<?php

namespace App\Enums;

/**
 * Abilities within a project. The values match the GraphQL enum ProjectAbility.
 *
 * Which role has which ability is defined in App\Support\ProjectPermissions.
 */
enum ProjectAbility: string
{
    case PROJECT_UPDATE = 'project.update';
    case PROJECT_DELETE = 'project.delete';
    case MEMBERS_MANAGE = 'members.manage';
    case MILESTONE_CREATE = 'milestone.create';
    case MILESTONE_UPDATE = 'milestone.update';
    case MILESTONE_DELETE = 'milestone.delete';
    case TODO_CREATE = 'todo.create';
    case TODO_UPDATE = 'todo.update';
    case TODO_DELETE = 'todo.delete';
    case TASK_CREATE = 'task.create';
    case TASK_UPDATE = 'task.update';
    case TASK_ASSIGN = 'task.assign';
    case TASK_MOVE = 'task.move';
    case TASK_DELETE = 'task.delete';
    case COMMENT_CREATE = 'comment.create';
    /** Delete comments written by someone else. Editing them is never allowed. */
    case COMMENT_MODERATE = 'comment.moderate';
    /**
     * Create, duplicate and change own label sets. Label sets are app-wide:
     * the ability counts if the user has it in any project.
     */
    case LABEL_SETS_MANAGE = 'label_sets.manage';
}
