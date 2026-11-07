<?php

namespace App\Enums;

/**
 * Kanban columns. Values match the GraphQL enum TaskStatus.
 */
enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Done = 'done';
}
