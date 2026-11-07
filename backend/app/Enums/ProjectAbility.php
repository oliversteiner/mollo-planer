<?php

namespace App\Enums;

/**
 * Abilities within a project. The values match the GraphQL enum ProjectAbility.
 *
 * Which role has which ability is defined in App\Support\ProjectPermissions.
 */
enum ProjectAbility: string
{
    case ProjectUpdate = 'project.update';
    case ProjectDelete = 'project.delete';
    case MembersManage = 'members.manage';
    case MilestoneCreate = 'milestone.create';
    case MilestoneUpdate = 'milestone.update';
    case MilestoneDelete = 'milestone.delete';
    case TodoCreate = 'todo.create';
    case TodoUpdate = 'todo.update';
    case TodoDelete = 'todo.delete';
    case TaskCreate = 'task.create';
    case TaskUpdate = 'task.update';
    case TaskAssign = 'task.assign';
    case TaskMove = 'task.move';
    case TaskDelete = 'task.delete';
    case CommentCreate = 'comment.create';
    /** Delete comments written by someone else. Editing them is never allowed. */
    case CommentModerate = 'comment.moderate';
}
