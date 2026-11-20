<?php

namespace App\Services;

use App\Enums\ReleaseStatus;
use App\Enums\TaskStatus;
use App\GraphQL\Resolvers\ProgressBatchLoader;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The numbers of the statistics page of a project (task 33). Everything is counted in the database (COUNT, GROUP
 * BY), never by loading the tasks, so the number of queries is fixed and small whatever the size of the project:
 * see ProjectStatisticsTest. Elements in the trash do not count (the scopes of the models). "Open" is every task
 * that is neither done nor an idea, as the progress counts: ideas are not planned work. The rounding of every
 * percentage is ProgressBatchLoader::percent, the counting columns of the progress are ProgressBatchLoader::withCounts.
 */
class ProjectStatistics
{
    /** Tasks due within this many days count as "due soon". */
    public const SOON_DAYS = 7;

    /** The value of a done task that has no commit (docs: planer format v2). */
    public const NO_COMMIT = 'none';

    /**
     * @return array<string, mixed>
     */
    public function for(Project $project): array
    {
        $id = $project->getKey();
        $byStatus = $this->tasksByStatus($id);
        $tasksTotal = array_sum(array_column($byStatus, 'count'));
        $milestones = $project->milestones()->get();
        $todosByMilestone = Todo::query()->where('project_id', $id)->toBase()
            ->selectRaw('milestone_id, count(*) as total')->groupBy('milestone_id')->pluck('total', 'milestone_id');
        $progress = $this->progressOf($id);

        return [
            'milestones' => $this->milestoneCounts($milestones),
            'todos' => (int) $todosByMilestone->sum(),
            'tasks' => $tasksTotal,
            'progress' => $progress,
            'tasksByStatus' => array_map(fn (array $row): array => $row + ['percent' => ProgressBatchLoader::percent($row['count'], $tasksTotal)], $byStatus),
            'tasksByLabel' => $this->tasksByLabel($project),
            'tasksByPerson' => $this->tasksByPerson($id),
            'due' => $this->due($id),
            'perMilestone' => $this->perMilestone($id, $milestones, $todosByMilestone),
            'withCommit' => Task::query()->inProject($id)->whereNotNull('commit')->where('commit', '!=', self::NO_COMMIT)->count(),
            'comments' => Comment::query()->whereIn('task_id', Task::query()->inProject($id)->select('tasks.id'))->count(),
        ];
    }

    /**
     * @return array{total: int, done: int, percent: int}
     */
    private function progressOf(int|string $id): array
    {
        $row = ProgressBatchLoader::withCounts(Task::query()->inProject($id)->toBase())->first();

        return ProgressBatchLoader::progress((int) $row?->total, (int) $row?->done);
    }

    /**
     * @return list<array{status: TaskStatus, count: int}>
     */
    private function tasksByStatus(int|string $id): array
    {
        $counts = Task::query()->inProject($id)->toBase()
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return array_map(fn (TaskStatus $status): array => ['status' => $status, 'count' => (int) ($counts[$status->value] ?? 0)], TaskStatus::cases());
    }

    /**
     * @param  Collection<int, Milestone>  $milestones
     * @return array{total: int, released: int, planned: int, unversioned: int}
     */
    private function milestoneCounts($milestones): array
    {
        return [
            'total' => $milestones->count(),
            'released' => $milestones->where('release_status', ReleaseStatus::RELEASED)->count(),
            'planned' => $milestones->where('release_status', ReleaseStatus::PLANNED)->count(),
            'unversioned' => $milestones->whereNull('release_version')->count(),
        ];
    }

    /**
     * Every label of the project, also with 0, in the order of the project; the tasks without a label last (label null).
     *
     * @return list<array{label: Label|null, count: int}>
     */
    private function tasksByLabel(Project $project): array
    {
        $counts = Task::query()->inProject($project->getKey())
            ->join('label_task', 'label_task.task_id', '=', 'tasks.id')
            ->toBase()->selectRaw('label_task.label_id as label_id, count(*) as total')->groupBy('label_task.label_id')
            ->pluck('total', 'label_id');

        $rows = $project->labels()->get()
            ->map(fn ($label): array => ['label' => $label, 'count' => (int) ($counts[$label->getKey()] ?? 0)])
            ->all();
        $rows[] = ['label' => null, 'count' => Task::query()->inProject($project->getKey())->whereDoesntHave('labels')->count()];

        return $rows;
    }

    /**
     * Open and done tasks per assigned person, the most open first; the tasks nobody has (user null) last.
     *
     * @return list<array{user: User|null, open: int, done: int}>
     */
    private function tasksByPerson(int|string $id): array
    {
        $rows = Task::query()->inProject($id)->toBase()
            ->selectRaw('assigned_user_id as user_id')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as done', [TaskStatus::DONE->value])
            ->selectRaw('sum(case when status in (?, ?) then 0 else 1 end) as open', [TaskStatus::DONE->value, TaskStatus::IDEA->value])
            ->groupBy('assigned_user_id')
            ->get()
            ->filter(fn (object $row): bool => (int) $row->open + (int) $row->done > 0);

        $users = User::query()->whereKey($rows->pluck('user_id')->filter()->all())->get()->keyBy('id');

        return $rows
            ->map(fn (object $row): array => ['user' => $row->user_id === null ? null : $users->get($row->user_id), 'open' => (int) $row->open, 'done' => (int) $row->done])
            ->filter(fn (array $row): bool => $row['user'] !== null || $row['open'] + $row['done'] > 0)
            ->sortBy([
                fn (array $a, array $b): int => ($a['user'] === null) <=> ($b['user'] === null),
                fn (array $a, array $b): int => $b['open'] <=> $a['open'],
                fn (array $a, array $b): int => strcasecmp((string) $a['user']?->name, (string) $b['user']?->name),
            ])
            ->values()
            ->all();
    }

    /**
     * Open tasks: overdue, due within SOON_DAYS days (today included), and without a date.
     *
     * @return array{overdue: int, soon: int, undated: int}
     */
    private function due(int|string $id): array
    {
        $today = Carbon::today();
        $row = Task::query()->inProject($id)->whereNotIn('status', [TaskStatus::IDEA->value, TaskStatus::DONE->value])->toBase()
            ->selectRaw('sum(case when due_date is null then 1 else 0 end) as undated')
            ->selectRaw('sum(case when due_date < ? then 1 else 0 end) as overdue', [$today->toDateString()])
            ->selectRaw('sum(case when due_date >= ? and due_date <= ? then 1 else 0 end) as soon', [$today->toDateString(), $today->copy()->addDays(self::SOON_DAYS)->toDateString()])
            ->first();

        return ['overdue' => (int) $row?->overdue, 'soon' => (int) $row?->soon, 'undated' => (int) $row?->undated];
    }

    /**
     * @param  Collection<int, Milestone>  $milestones
     * @param  Collection<int|string, int>  $todosByMilestone
     * @return list<array<string, mixed>>
     */
    private function perMilestone(int|string $id, $milestones, $todosByMilestone): array
    {
        $counts = ProgressBatchLoader::withCounts(
            Task::query()->inProject($id)
                ->select(['group_key' => Todo::query()->select('todos.milestone_id')->whereColumn('todos.id', 'tasks.todo_id')])
                ->toBase(),
        )->selectRaw('count(*) as all_tasks')->groupBy('group_key')->get()->keyBy('group_key');

        return $milestones->map(function (Milestone $milestone) use ($counts, $todosByMilestone): array {
            $row = $counts->get($milestone->getKey());

            return [
                'milestone' => $milestone,
                'todos' => (int) ($todosByMilestone[$milestone->getKey()] ?? 0),
                'tasks' => (int) $row?->all_tasks,
                'progress' => ProgressBatchLoader::progress((int) $row?->total, (int) $row?->done),
            ];
        })->all();
    }
}
