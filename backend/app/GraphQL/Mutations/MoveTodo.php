<?php

namespace App\GraphQL\Mutations;

use App\Models\Milestone;
use App\Models\Todo;
use App\Services\Ordering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves a todo to another place: inside its milestone, or to another milestone
 * of the same project. The others keep their order and move along. The status of
 * the old and the new milestone is recomputed by the observers.
 */
class MoveTodo
{
    /**
     * @param  array{id: int|string, milestoneId: int|string, position: int}  $args
     *
     * @throws ValidationException
     */
    public function __invoke(mixed $root, array $args): Todo
    {
        return DB::transaction(function () use ($args): Todo {
            $todo = Todo::findOrFail($args['id']);
            $target = Milestone::findOrFail($args['milestoneId']);

            if ($target->project_id !== $todo->project_id) {
                throw ValidationException::withMessages(['milestoneId' => __('projects.milestone_other_project')]);
            }

            $sourceMilestoneId = $todo->milestone_id;
            $todo->milestone()->associate($target);

            app(Ordering::class)->move(
                $todo,
                $todo->project_id,
                Todo::query()->where('milestone_id', $target->getKey()),
                (int) $args['position'],
                (string) $sourceMilestoneId !== (string) $target->getKey()
                    ? Todo::query()->where('milestone_id', $sourceMilestoneId)
                    : null,
            );

            return $todo->refresh();
        });
    }
}
