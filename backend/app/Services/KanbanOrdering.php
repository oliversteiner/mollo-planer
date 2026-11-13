<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;

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
    /** The column that holds the place of a task in its Kanban column. */
    public const COLUMN = 'board_position';

    public function __construct(private readonly Ordering $ordering) {}

    /**
     * Position for a new task at the end of a column.
     */
    public function nextPosition(int|string $projectId, TaskStatus $status): int
    {
        return $this->ordering->nextPosition($projectId, $this->column($projectId, $status), self::COLUMN);
    }

    /**
     * Move a task to a column and position. Positions beyond the end place
     * the task last.
     */
    public function move(Task $task, TaskStatus $status, int $position): Task
    {
        $projectId = $task->owningProject()->getKey();
        $this->ordering->lockProject($projectId);
        // Read the status again: another move may have changed it before the lock.
        $task->refresh();
        $oldStatus = $task->status;

        $task->status = $status;

        $this->ordering->move(
            $task,
            $projectId,
            $this->column($projectId, $status),
            $position,
            $oldStatus !== $status ? $this->column($projectId, $oldStatus) : null,
            self::COLUMN,
        );

        return $task;
    }

    /**
     * @return Builder<Task>
     */
    private function column(int|string $projectId, TaskStatus $status): Builder
    {
        return Task::query()->inProject($projectId)->where('status', $status->value);
    }
}
