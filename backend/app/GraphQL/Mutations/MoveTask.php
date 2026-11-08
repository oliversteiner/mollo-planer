<?php

namespace App\GraphQL\Mutations;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\KanbanOrdering;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(fn (): Task => $this->ordering->move(
            Task::findOrFail($args['id']),
            TaskStatus::from($args['status']),
            $args['position'],
        ));
    }
}
