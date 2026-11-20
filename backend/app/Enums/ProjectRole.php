<?php

namespace App\Enums;

/**
 * Keys of the project roles shipped with the app (config/permissions.php).
 * Only seeders, factories and tests name them, and the old project plan
 * (GraphQL enum ProjectRole, replaced in steps I1 to I3). The app itself asks
 * App\Support\Rights for rights and reads roles from the table roles.
 */
enum ProjectRole: string
{
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case MEMBER = 'member';
    /** Reads the project, changes nothing (the standard rights are empty). */
    case GUEST = 'guest';
    /** An agent account (Claude): plan and tasks, export and import, no members and no project settings. */
    case AGENT = 'agent';
}
