<?php

namespace App\GraphQL\Resolvers;

use App\Enums\TaskStatus;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Progress of a project, milestone or todo: done tasks out of all tasks.
 * Computed on request, never stored.
 */
class Progress
{
    /**
     * @return array{total: int, done: int, percent: int}
     */
    public function project(Project $project): array
    {
        return $this->count(Task::query()->inProject($project->getKey()));
    }

    /**
     * @return array{total: int, done: int, percent: int}
     */
    public function milestone(Milestone $milestone): array
    {
        return $this->count(Task::query()->whereIn('todo_id', $milestone->todos()->select('id')));
    }

    /**
     * @return array{total: int, done: int, percent: int}
     */
    public function todo(Todo $todo): array
    {
        return $this->count(Task::query()->where('todo_id', $todo->getKey()));
    }

    /**
     * @param  Builder<Task>  $tasks
     * @return array{total: int, done: int, percent: int}
     */
    private function count(Builder $tasks): array
    {
        $row = $tasks->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as done', [TaskStatus::DONE->value])
            ->first();

        $total = (int) ($row->total ?? 0);
        $done = (int) ($row->done ?? 0);

        return [
            'total' => $total,
            'done' => $done,
            'percent' => $total === 0 ? 0 : (int) round($done * 100 / $total),
        ];
    }
}
