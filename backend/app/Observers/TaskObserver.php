<?php

namespace App\Observers;

use App\Models\Task;
use App\Observers\Concerns\TouchesPlanerRevision;
use App\Services\ItemStatusSync;
use App\Services\ReleaseRules;

/**
 * Lifecycle of a task in one place: the rules of releases and commits, and
 * the todo status, which follows its tasks.
 */
class TaskObserver
{
    use TouchesPlanerRevision;

    public function __construct(private readonly ItemStatusSync $statusSync) {}

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

    public function deleted(Task $task): void
    {
        $this->touchRevision($task->project_id);

        $this->statusSync->syncTodo($task->todo_id);
    }
}
