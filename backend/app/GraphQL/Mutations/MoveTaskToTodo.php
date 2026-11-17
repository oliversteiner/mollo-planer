<?php

namespace App\GraphQL\Mutations;

use App\Models\Task;
use App\Models\Todo;
use App\Planer\PlanerLimits;
use App\Services\Ordering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a task to another todo of the same project (drag and drop in the
 * timeline). Status and Kanban position stay, the place in the todo is the end; the observer recomputes the
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

            PlanerLimits::assertTaskRoom($todo->getKey());

            $sourceTodoId = $task->todo_id;
            $task->todo()->associate($todo);

            // To the end of the new todo; the old one closes the gap.
            app(Ordering::class)->move(
                $task,
                $task->project_id,
                Task::query()->where('todo_id', $todo->getKey()),
                PHP_INT_MAX,
                Task::query()->where('todo_id', $sourceTodoId),
            );

            return $task;
        });
    }
}
