<?php

namespace App\Observers;

use App\Models\Todo;
use App\Observers\Concerns\TouchesPlanerRevision;
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
     * A deleted todo leaves no gap in its milestone. Its tasks are removed by
     * the database (cascade), without events.
     */
    public function deleted(Todo $todo): void
    {
        $this->ordering->close($todo->project_id, Todo::query()->where('milestone_id', $todo->milestone_id));
        $this->statusSync->syncMilestone($todo->milestone_id);
        $this->touchRevision($todo->project_id);
    }
}
