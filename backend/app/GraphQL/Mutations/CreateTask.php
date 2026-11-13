<?php

namespace App\GraphQL\Mutations;

use App\Enums\TaskStatus;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Task;
use App\Models\Todo;
use App\Services\KanbanOrdering;
use App\Services\ProjectMembership;
use App\Services\TaskLabels;
use App\Services\TaskNotifier;
use App\Support\Rights;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Creates a task in the OPEN column, at the end. An assignee must be a
 * member of the project and gets a mail.
 */
class CreateTask
{
    use ResolvesViewer;

    public function __construct(
        private readonly ProjectMembership $membership,
        private readonly KanbanOrdering $ordering,
        private readonly TaskNotifier $notifier,
        private readonly TaskLabels $labels,
    ) {}

    /**
     * @param  array<string, mixed>  $args  todo_id, title, description, icon, due_date, assigned_user_id, label_ids
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): Task
    {
        $viewer = $this->viewer($context);

        return DB::transaction(function () use ($args, $viewer): Task {
            $todo = Todo::findOrFail($args['todo_id']);
            $project = $todo->owningProject();
            $assigneeId = $args['assigned_user_id'] ?? null;

            if (! Rights::mayAssignTask($viewer, $project, $assigneeId, null)) {
                throw new AuthorizationException;
            }

            if ($assigneeId !== null) {
                $this->membership->ensureAssignable($project, $assigneeId, 'input.assigneeId');
            }

            $labelIds = $this->labels->validated($project, $args['label_ids'] ?? [], null, 'input.labelIds');

            $task = new Task([
                'title' => $args['title'],
                'description' => $args['description'] ?? null,
                'icon' => $args['icon'] ?? null,
                'due_date' => $args['due_date'] ?? null,
                'user_story' => $args['user_story'] ?? null,
            ]);
            $task->todo()->associate($todo);
            $task->assigned_user_id = $assigneeId;
            $task->status = TaskStatus::OPEN;
            $task->board_position = $this->ordering->nextPosition($project->getKey(), TaskStatus::OPEN);
            $task->save();
            $task->labels()->sync($labelIds);

            if ($assigneeId !== null) {
                $this->notifier->assigned($task, $viewer);
            }

            return $task;
        });
    }
}
