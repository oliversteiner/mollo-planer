<?php

namespace App\GraphQL\Mutations;

use App\Models\Task;
use App\Services\ProjectMembership;

/**
 * Assigns a task to a project member, or removes the assignment when
 * assigneeId is null. Assigning the same person again changes nothing.
 */
class AssignTask
{
    public function __construct(private readonly ProjectMembership $membership) {}

    /**
     * @param  array{id: int|string, assigneeId?: int|string|null}  $args
     */
    public function __invoke(mixed $root, array $args): Task
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

        return $task;
    }
}
