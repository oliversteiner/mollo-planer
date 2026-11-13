<?php

namespace App\GraphQL\Mutations;

use App\Enums\TaskStatus;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Task;
use App\Services\KanbanOrdering;
use App\Services\TaskNotifier;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Moves a task in the Kanban. Leaving IDEA for another status sends the
 * assignment mail (planer-abgleich §8.2, Nr. 5), if the task has an assignee.
 */
class MoveTask
{
    use ResolvesViewer;

    public function __construct(
        private readonly KanbanOrdering $ordering,
        private readonly TaskNotifier $notifier,
    ) {}

    /**
     * @param  array{id: int|string, status: string, position: int}  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): Task
    {
        $viewer = $this->viewer($context);

        return DB::transaction(function () use ($args, $viewer): Task {
            $task = Task::findOrFail($args['id']);
            $wasIdea = $task->status === TaskStatus::IDEA;

            $moved = $this->ordering->move($task, TaskStatus::from($args['status']), $args['position']);

            if ($wasIdea && $moved->status !== TaskStatus::IDEA && $moved->assigned_user_id !== null) {
                $this->notifier->assigned($moved, $viewer);
            }

            return $moved;
        });
    }
}
