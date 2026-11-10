<?php

namespace App\Enums;

/**
 * Abilities across the whole app, granted by the global UserRole. The values
 * match the GraphQL enum SystemAbility.
 *
 * Which role has which ability is defined in App\Support\SystemPermissions.
 */
enum SystemAbility: string
{
    case PROJECTS_CREATE = 'projects.create';
    /** See every project, even without membership. */
    case PROJECTS_VIEW_ALL = 'projects.viewAll';
    /** Override all project rules (owner rules, last owner, every project ability). */
    case PROJECTS_MANAGE_ANY = 'projects.manageAny';
    /** Full user list, create, change and delete users, app settings. */
    case USERS_MANAGE = 'users.manage';
    /** Create and duplicate label sets. */
    case LABEL_SETS_MANAGE = 'labelSets.manage';
    /** Change and delete label sets of others, including built-in ones. */
    case LABEL_SETS_MANAGE_ANY = 'labelSets.manageAny';
}
