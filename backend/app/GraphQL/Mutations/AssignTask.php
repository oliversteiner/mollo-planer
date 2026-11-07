<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Task;
use App\Services\ProjectMembership;
use App\Services\TaskNotifier;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Assigns a task to a project member, or removes the assignment when
 * assigneeId is null. Assigning the same person again changes nothing and
 * sends no mail.
 */
class AssignTask
{
    use ResolvesViewer;

    public function __construct(
        private readonly ProjectMembership $membership,
        private readonly TaskNotifier $notifier,
    ) {}

    /**
     * @param  array{id: int|string, assigneeId?: int|string|null}  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): Task
    {
        $task = Task::findOrFail($args['id']);
        $assigneeId = $args['assigneeId'] ?? null;

        if ($assigneeId !== null) {
            $this->membership->ensureAssignable($task->owningProject(), $assigneeId, 'assigneeId');
        }

        if ((string) $task->assigned_user_id === (string) $assigneeId) {
            return $task;
        }

        $task->assigned_user_id = $assigneeId;
        $task->save();

        if ($assigneeId !== null) {
            $this->notifier->assigned($task, $this->viewer($context));
        }

        return $task;
    }
}
