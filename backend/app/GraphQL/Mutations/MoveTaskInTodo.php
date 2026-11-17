<?php

namespace App\GraphQL\Mutations;

use App\Models\Task;
use App\Services\Ordering;
use Illuminate\Support\Facades\DB;

/**
 * Moves a task to another place in its todo (the order of the planer file).
 * The others keep their relative order; the positions are renumbered from 0.
 * Status and Kanban position do not change.
 */
class MoveTaskInTodo
{
    /**
     * @param  array{id: int|string, position: int}  $args
     */
    public function __invoke(mixed $root, array $args): Task
    {
        return DB::transaction(function () use ($args): Task {
            $task = Task::findOrFail($args['id']);

            app(Ordering::class)->move(
                $task,
                $task->project_id,
                Task::query()->where('todo_id', $task->todo_id),
                (int) $args['position'],
            );

            return $task->refresh();
        });
    }
}
