<?php

namespace App\Enums;

/**
 * Global role of a user (users.role). It decides what someone may do in the
 * whole app. What a role may do is defined only in App\Support\SystemPermissions.
 */
enum UserRole: string
{
    case ADMIN = 'admin';
    case PROJEKTLEITUNG = 'projektleitung';
    case KOORDINATION = 'koordination';
    case MITGLIED = 'mitglied';
    /** Read only: may not change anything, whatever the project role. */
    case LESEND = 'lesend';
}
