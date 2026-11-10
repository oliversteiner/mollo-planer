<?php

namespace App\Enums;

/**
 * Job function of a user (users.job_function). Display only: it grants no
 * rights. The rights come from UserRole and the project role.
 */
enum UserFunction: string
{
    case DESIGNER = 'designer';
    case ENTWICKLUNG = 'entwicklung';
    case TESTER = 'tester';
    case CONTENT = 'content';
    case TECHNIK = 'technik';
    case VOLUNTEER = 'volunteer';
}
