<?php

namespace App\GraphQL\Mutations;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\KanbanOrdering;

/**
 * Moves a task to another column and/or position in the Kanban board.
 */
class MoveTask
{
    public function __construct(private readonly KanbanOrdering $ordering) {}

    /**
     * @param  array{id: int|string, status: string, position: int}  $args
     */
    public function __invoke(mixed $root, array $args): Task
    {
        $task = Task::findOrFail($args['id']);

        return $this->ordering->move($task, TaskStatus::from($args['status']), $args['position']);
    }
}
