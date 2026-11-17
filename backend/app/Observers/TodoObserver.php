<?php

namespace App\Observers;

use App\Models\Todo;
use App\Observers\Concerns\TouchesPlanerRevision;
use App\Planer\PlanerLimits;
use App\Services\ItemStatusSync;
use App\Services\Ordering;

/**
 * Lifecycle of a todo in one place: its position in the milestone and the
 * milestone status, which follows its todos.
 *
 * Status changes of the todo itself are written quietly by ItemStatusSync,
 * which then syncs the milestone directly.
 */
class TodoObserver
{
    use TouchesPlanerRevision;

    public function __construct(
        private readonly ItemStatusSync $statusSync,
        private readonly Ordering $ordering,
    ) {}

    /**
     * A new todo goes to the end of its milestone, or to the given position;
     * the todos from there on move one place back.
     */
    public function creating(Todo $todo): void
    {
        PlanerLimits::assertTodoRoom($todo->milestone_id);

        $group = Todo::query()->where('milestone_id', $todo->milestone_id);

        if ($todo->position === null) {
            $todo->position = $this->ordering->nextPosition($todo->project_id, $group);

            return;
        }

        $this->ordering->openSlot($todo->project_id, $group, $todo->position);
    }

    /**
     * A new todo has no tasks yet and is open, so the milestone reopens.
     */
    public function created(Todo $todo): void
    {
        $this->statusSync->syncMilestone($todo->milestone_id);
        $this->touchRevision($todo->project_id);
    }

    public function updated(Todo $todo): void
    {
        $this->touchRevision($todo->project_id);

        if ($todo->wasChanged('milestone_id')) {
            $this->statusSync->syncMilestone($todo->getOriginal('milestone_id'));
            $this->statusSync->syncMilestone($todo->milestone_id);
        }
    }

    /**
     * A todo moved to the trash leaves no gap in its milestone, and the
     * milestone status follows. Its tasks and comments went with it
     * (App\Services\Deletion, without events).
     */
    public function deleted(Todo $todo): void
    {
        if ($todo->isForceDeleting()) {
            return;
        }

        $this->ordering->close($todo->project_id, Todo::query()->where('milestone_id', $todo->milestone_id));
        $this->statusSync->syncMilestone($todo->milestone_id);
        $this->touchRevision($todo->project_id);
    }

    /** Back from the trash: at the end of its milestone. */
    public function restoring(Todo $todo): void
    {
        $todo->position = $this->ordering->nextPosition($todo->project_id, Todo::query()->where('milestone_id', $todo->milestone_id));
    }

    /** Its status and the milestone's follow the tasks that came back. */
    public function restored(Todo $todo): void
    {
        $this->statusSync->syncTodo($todo->getKey());
        $this->statusSync->syncMilestone($todo->milestone_id);
        $this->touchRevision($todo->project_id);
    }

    /** Gone for good, with its tasks (cascade): nothing to order, nothing in the file. */
    public function forceDeleted(Todo $todo): void {}
}
