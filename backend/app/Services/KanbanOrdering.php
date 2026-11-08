<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;

/**
 * Order of tasks within the Kanban columns of a project.
 *
 * A column is "all tasks of one project with one status". Positions start
 * at 0 and have no gaps after a move. Deleting a task may leave a gap; the
 * order stays correct because columns are sorted by position.
 *
 * Call inside a transaction: changes lock the project row, so two moves
 * or new tasks in the same project run one after the other.
 */
class KanbanOrdering
{
    /**
     * Position for a new task at the end of a column.
     */
    public function nextPosition(int|string $projectId, TaskStatus $status): int
    {
        $this->lockProject($projectId);

        $max = Task::query()->inProject($projectId)->where('status', $status->value)->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }

    /**
     * Move a task to a column and position. Positions beyond the end place
     * the task last.
     */
    public function move(Task $task, TaskStatus $status, int $position): Task
    {
        $projectId = $task->owningProject()->getKey();
        $this->lockProject($projectId);
        // Read the status again: another move may have changed it before the lock.
        $task->refresh();
        $oldStatus = $task->status;

        $target = Task::query()
            ->inProject($projectId)
            ->where('status', $status->value)
            ->whereKeyNot($task->getKey())
            ->orderBy('position')
            ->orderBy('id')
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
                ->get();

            $this->renumber($source->all());
        }

        return $task;
    }

    private function lockProject(int|string $projectId): void
    {
        Project::query()->whereKey($projectId)->lockForUpdate()->first();
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
