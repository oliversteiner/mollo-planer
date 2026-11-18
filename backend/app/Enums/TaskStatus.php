<?php

namespace App\Enums;

/**
 * Kanban columns. Values match the GraphQL enum TaskStatus.
 *
 * IDEA does not count for the status or the progress of its todo, and
 * assignment mails wait until the task leaves IDEA (planer-abgleich §8.2).
 */
enum TaskStatus: string
{
    case IDEA = 'idea';
    case PLANNED = 'planned';
    case OPEN = 'open';
    case IN_PROGRESS = 'in_progress';
    case REVIEW = 'review';
    case DONE = 'done';
}
