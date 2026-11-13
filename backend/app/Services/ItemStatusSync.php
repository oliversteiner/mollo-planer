<?php

namespace App\Services;

use App\Enums\ItemStatus;
use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\Todo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Derives the status of todos and milestones from their children.
 *
 * A todo is DONE when it has tasks and all of them are done, otherwise
 * OPEN. A milestone is DONE when it has todos and all of them are done.
 * This is the only place that writes these statuses.
 */
class ItemStatusSync
{
    /**
     * Recompute a todo and, if its status changed, its milestone.
     */
    public function syncTodo(?int $todoId): void
    {
        $todo = $todoId === null ? null : Todo::find($todoId);

        if ($todo === null) {
            return;
        }

        $status = $this->derive($todo->tasks(), TaskStatus::DONE->value, TaskStatus::IDEA->value);

        if ($todo->getRawOriginal('status') !== $status->value) {
            $todo->status = $status;
            // Quietly: the todo observer reacts to structural changes only.
            $todo->saveQuietly();
            $this->syncMilestone($todo->milestone_id);
        }
    }

    /**
     * Recompute a milestone.
     */
    public function syncMilestone(?int $milestoneId): void
    {
        $milestone = $milestoneId === null ? null : Milestone::find($milestoneId);

        if ($milestone === null) {
            return;
        }

        $status = $this->derive($milestone->todos(), ItemStatus::DONE->value);

        if ($milestone->getRawOriginal('status') !== $status->value) {
            $milestone->status = $status;
            $milestone->saveQuietly();
        }
    }

    /**
     * DONE if there is at least one child and every child is done. Children
     * with the ignored value (tasks in IDEA) do not count at all.
     *
     * @param  HasMany<Task, Todo>|HasMany<Todo, Milestone>  $children
     */
    private function derive(HasMany $children, string $doneValue, ?string $ignoredValue = null): ItemStatus
    {
        $counted = $children->clone();
        if ($ignoredValue !== null) {
            $counted->where('status', '!=', $ignoredValue);
        }

        $hasChildren = $counted->clone()->exists();
        $hasUnfinished = $counted->clone()->where('status', '!=', $doneValue)->exists();

        return $hasChildren && ! $hasUnfinished ? ItemStatus::DONE : ItemStatus::OPEN;
    }
}
