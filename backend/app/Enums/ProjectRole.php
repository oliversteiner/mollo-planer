<?php

namespace App\Enums;

/**
 * Role of a user within a project (pivot column project_user.role).
 *
 * The role alone grants nothing. What a role may do is defined only in
 * App\Support\Rights.
 */
enum ProjectRole: string
{
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case MEMBER = 'member';
    /** Reads the project, changes nothing (the standard rights are empty). */
    case GUEST = 'guest';
}
