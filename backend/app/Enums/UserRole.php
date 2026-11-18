<?php

namespace App\Enums;

/**
 * Keys of the system roles shipped with the app (config/permissions.php).
 * Only seeders, factories, the first-admin command and tests name them. The
 * app itself asks App\Support\Rights for rights and reads roles from the
 * table roles (App\Models\Role); roles made in the settings are not here.
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
