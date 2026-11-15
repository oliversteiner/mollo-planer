<?php

namespace App\Enums;

/**
 * Activity of a user (users.activity). Display only: it grants no
 * rights. The rights come from UserRole and the project role.
 */
enum UserActivity: string
{
    case DESIGNER = 'designer';
    case ENTWICKLUNG = 'entwicklung';
    case TESTER = 'tester';
    case CONTENT = 'content';
    case TECHNIK = 'technik';
    case VOLUNTEER = 'volunteer';
}
