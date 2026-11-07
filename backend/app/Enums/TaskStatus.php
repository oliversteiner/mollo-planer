<?php

namespace App\Enums;

/**
 * Kanban columns. Values match the GraphQL enum TaskStatus.
 */
enum TaskStatus: string
{
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case REVIEW = 'review';
    case DONE = 'done';
}
