<?php

namespace App\Mcp\Support;

use App\Enums\TaskStatus;

/**
 * Sets the status of a task through the GraphQL mutation moveTask, the same
 * path as the Kanban board: task.move permission, KanbanOrdering and the
 * observers that recalculate todo and milestone. Nothing else is changed.
 */
final class TaskStatusChange
{
    /** Larger than any column; KanbanOrdering clamps the position, so the task lands at the end. */
    private const END_OF_COLUMN = 2147483647;

    /**
     * @return array{id: string, status: string, position: int}
     */
    public static function variables(string $taskId, TaskStatus $status): array
    {
        return ['id' => $taskId, 'status' => $status->name, 'position' => self::END_OF_COLUMN];
    }

    /**
     * @param  array<string, mixed>  $task  The moveTask result
     * @return array<string, mixed>
     */
    public static function summarize(array $task): array
    {
        return [
            'task_id' => (string) $task['id'],
            'status' => strtolower((string) $task['status']),
            'todo' => [
                'id' => (string) $task['todo']['id'],
                'status' => strtolower((string) $task['todo']['status']),
            ],
            'milestone' => [
                'id' => (string) $task['todo']['milestone']['id'],
                'status' => strtolower((string) $task['todo']['milestone']['status']),
            ],
        ];
    }
}
