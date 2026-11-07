<?php

namespace App\Observers;

use App\Models\Todo;
use App\Services\ItemStatusSync;

/**
 * Keeps the milestone status in line with its todos.
 *
 * Status changes of the todo itself are written quietly by ItemStatusSync,
 * which then syncs the milestone directly.
 */
class TodoObserver
{
    public function __construct(private readonly ItemStatusSync $statusSync) {}

    /**
     * A new todo has no tasks yet and is open, so the milestone reopens.
     */
    public function created(Todo $todo): void
    {
        $this->statusSync->syncMilestone($todo->milestone_id);
    }

    public function updated(Todo $todo): void
    {
        if ($todo->wasChanged('milestone_id')) {
            $this->statusSync->syncMilestone($todo->getOriginal('milestone_id'));
            $this->statusSync->syncMilestone($todo->milestone_id);
        }
    }

    /**
     * Its tasks are removed by the database (cascade), without events.
     */
    public function deleted(Todo $todo): void
    {
        $this->statusSync->syncMilestone($todo->milestone_id);
    }
}
