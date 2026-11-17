<?php

namespace App\Observers;

use App\Models\Task;
use App\Observers\Concerns\TouchesPlanerRevision;
use App\Planer\PlanerLimits;
use App\Services\ItemStatusSync;
use App\Services\KanbanOrdering;
use App\Services\ReleaseRules;

/**
 * Lifecycle of a task in one place: the rules of releases and commits, its
 * place in the Kanban column, and the todo status, which follows its tasks.
 * A task in the trash counts nowhere; back from the trash it goes to the end
 * of its column. Deleting it for good changes neither order nor planer file.
 */
class TaskObserver
{
    use TouchesPlanerRevision;

    public function __construct(
        private readonly ItemStatusSync $statusSync,
        private readonly KanbanOrdering $kanban,
    ) {}

    public function creating(Task $task): void
    {
        PlanerLimits::assertTaskRoom($task->todo_id);
    }

    public function saving(Task $task): void
    {
        ReleaseRules::checkTask($task);
    }

    public function created(Task $task): void
    {
        $this->touchRevision($task->project_id);

        $this->statusSync->syncTodo($task->todo_id);
    }

    public function updated(Task $task): void
    {
        $this->touchRevision($task->project_id);

        if ($task->wasChanged('todo_id')) {
            $this->statusSync->syncTodo($task->getOriginal('todo_id'));
            $this->statusSync->syncTodo($task->todo_id);

            return;
        }

        if ($task->wasChanged('status')) {
            $this->statusSync->syncTodo($task->todo_id);
        }
    }

    /** Moved to the trash: no gap in its column, the todo status follows. */
    public function deleted(Task $task): void
    {
        if ($task->isForceDeleting()) {
            return;
        }

        $this->kanban->close($task->project_id, $task->status);
        $this->touchRevision($task->project_id);

        $this->statusSync->syncTodo($task->todo_id);
    }

    /** Back from the trash: at the end of its column. */
    public function restoring(Task $task): void
    {
        $task->board_position = $this->kanban->nextPosition($task->project_id, $task->status);
    }

    public function restored(Task $task): void
    {
        $this->touchRevision($task->project_id);

        $this->statusSync->syncTodo($task->todo_id);
    }

    /** Gone for good, with its comments (cascade). */
    public function forceDeleted(Task $task): void {}
}
