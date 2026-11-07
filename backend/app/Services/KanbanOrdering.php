<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;

/**
 * Order of tasks within the Kanban columns of a project.
 *
 * A column is "all tasks of one project with one status". Positions start
 * at 0 and have no gaps after a move. Deleting a task may leave a gap; the
 * order stays correct because columns are sorted by position.
 */
class KanbanOrdering
{
    /**
     * Position for a new task at the end of a column.
     */
    public function nextPosition(int|string $projectId, TaskStatus $status): int
    {
        $max = Task::query()->inProject($projectId)->where('status', $status->value)->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }

    /**
     * Move a task to a column and position. Positions beyond the end place
     * the task last. Runs inside the mutation transaction; the rows of both
     * columns are locked to avoid two moves interleaving.
     */
    public function move(Task $task, TaskStatus $status, int $position): Task
    {
        $projectId = $task->owningProject()->getKey();
        $oldStatus = $task->status;

        $target = Task::query()
            ->inProject($projectId)
            ->where('status', $status->value)
            ->whereKeyNot($task->getKey())
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $position = max(0, min($position, $target->count()));
        $ordered = $target->values()->all();
        array_splice($ordered, $position, 0, [$task]);

        $task->status = $status;
        $this->renumber($ordered);

        if ($oldStatus !== $status) {
            $source = Task::query()
                ->inProject($projectId)
                ->where('status', $oldStatus->value)
                ->orderBy('position')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->renumber($source->all());
        }

        return $task;
    }

    /**
     * Save positions 0..n. The moved task is saved normally so the status
     * observer runs; the others change only their position.
     *
     * @param  list<Task>  $tasks
     */
    private function renumber(array $tasks): void
    {
        foreach ($tasks as $index => $task) {
            $task->position = $index;

            if ($task->isDirty()) {
                $task->save();
            }
        }
    }
}
