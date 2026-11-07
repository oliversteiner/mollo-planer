<?php

namespace App\Enums;

/**
 * Status of milestones and todos. Computed by App\Services\ItemStatusSync,
 * never set from input. Values match the GraphQL enum ItemStatus.
 */
enum ItemStatus: string
{
    case Open = 'open';
    case Done = 'done';
}
