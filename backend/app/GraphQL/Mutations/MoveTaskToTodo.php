<?php

namespace App\GraphQL\Mutations;

use App\Models\Task;
use App\Models\Todo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a task to another todo of the same project (drag and drop in the
 * timeline). Status and Kanban position stay; the observer recomputes the
 * status of the old and the new todo and their milestones.
 */
class MoveTaskToTodo
{
    /**
     * @param  array{id: int|string, todoId: int|string}  $args
     */
    public function __invoke(mixed $root, array $args): Task
    {
        return DB::transaction(function () use ($args): Task {
            $task = Task::findOrFail($args['id']);
            $todo = Todo::find($args['todoId']);

            if ($todo === null || ! $todo->owningProject()->is($task->owningProject())) {
                throw ValidationException::withMessages(['todoId' => __('projects.todo_other_project')]);
            }

            if ((string) $task->todo_id === (string) $todo->getKey()) {
                return $task;
            }

            $task->todo()->associate($todo);
            $task->save();

            return $task;
        });
    }
}
