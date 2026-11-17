<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;

/**
 * Order of tasks within the Kanban columns of a project.
 *
 * A column is "all tasks of one project with one status". Positions start
 * at 0 and have no gaps after a move or a deletion (tasks in the trash count
 * nowhere).
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
     * Renumbers the given columns from 0, for example after tasks went to
     * the trash. Without statuses: every column of the project.
     */
    public function close(int|string $projectId, TaskStatus ...$statuses): void
    {
        foreach ($statuses === [] ? TaskStatus::cases() : $statuses as $status) {
            $this->ordering->close($projectId, $this->column($projectId, $status), self::COLUMN);
        }
    }

    /**
     * Puts tasks back from the trash at the end of their columns, in their
     * old order. Written quietly: the restore counts as one change.
     *
     * @param  iterable<Task>  $tasks
     */
    public function append(int|string $projectId, iterable $tasks): void
    {
        $byStatus = collect($tasks)->sortBy([['board_position', 'asc'], ['id', 'asc']])->groupBy(fn (Task $task) => $task->status->value);

        foreach ($byStatus as $status => $group) {
            $ids = $group->pluck('id')->all();
            $next = $this->ordering->nextPosition($projectId, $this->column($projectId, TaskStatus::from($status))->whereKeyNot($ids), self::COLUMN);

            foreach ($group as $task) {
                $task->board_position = $next++;
                $task->saveQuietly();
            }
        }
    }

    /**
     * @return Builder<Task>
     */
    private function column(int|string $projectId, TaskStatus $status): Builder
    {
        return Task::query()->inProject($projectId)->where('status', $status->value);
    }
}
