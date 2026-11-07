<?php

namespace App\GraphQL\Mutations;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\Todo;
use App\Services\KanbanOrdering;
use App\Services\ProjectMembership;

/**
 * Creates a task in the OPEN column, at the end. An assignee must be a
 * member of the project.
 */
class CreateTask
{
    public function __construct(
        private readonly ProjectMembership $membership,
        private readonly KanbanOrdering $ordering,
    ) {}

    /**
     * @param  array<string, mixed>  $args  todo_id, title, description, icon, due_date, assigned_user_id
     */
    public function __invoke(mixed $root, array $args): Task
    {
        $todo = Todo::findOrFail($args['todo_id']);
        $project = $todo->owningProject();
        $assigneeId = $args['assigned_user_id'] ?? null;

        if ($assigneeId !== null) {
            $this->membership->ensureAssignable($project, $assigneeId, 'input.assigneeId');
        }

        $task = new Task([
            'title' => $args['title'],
            'description' => $args['description'] ?? null,
            'icon' => $args['icon'] ?? null,
            'due_date' => $args['due_date'] ?? null,
        ]);
        $task->todo()->associate($todo);
        $task->assigned_user_id = $assigneeId;
        $task->status = TaskStatus::OPEN;
        $task->position = $this->ordering->nextPosition($project->getKey(), TaskStatus::OPEN);
        $task->save();

        return $task;
    }
}
