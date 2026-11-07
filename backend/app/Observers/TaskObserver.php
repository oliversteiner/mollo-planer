<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\ItemStatusSync;

/**
 * Keeps the todo status in line with its tasks.
 */
class TaskObserver
{
    public function __construct(private readonly ItemStatusSync $statusSync) {}

    public function created(Task $task): void
    {
        $this->statusSync->syncTodo($task->todo_id);
    }

    public function updated(Task $task): void
    {
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
        $this->statusSync->syncTodo($task->todo_id);
    }
}
