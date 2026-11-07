<?php

namespace App\Enums;

/**
 * Role of a user within a project (pivot column project_user.role).
 *
 * The role alone grants nothing. What a role may do is defined only in
 * App\Support\ProjectPermissions.
 */
enum ProjectRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Member = 'member';
}
