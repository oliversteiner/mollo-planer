<?php

namespace App\GraphQL\Mutations;

use App\Models\Task;
use App\Services\TaskLabels;
use Illuminate\Support\Facades\DB;

/**
 * Sets the labels of a task (all at once). Allowed by TASK_UPDATE (@can).
 */
class SetTaskLabels
{
    public function __construct(private readonly TaskLabels $labels) {}

    /**
     * @param  array{taskId: int|string, labelIds: list<int|string>}  $args
     */
    public function __invoke(mixed $root, array $args): Task
    {
        return DB::transaction(function () use ($args): Task {
            $task = Task::findOrFail($args['taskId']);
            $ids = $this->labels->validated($task->owningProject(), $args['labelIds'], $task, 'labelIds');

            $task->labels()->sync($ids);

            return $task->load('labels');
        });
    }
}
