<?php

namespace App\Services;

use App\Enums\DocumentAccess;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Idea;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Support\RichTextDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * What the trash shows (GraphQL TrashItem): one entry per deletion, the
 * element it started with, how many others went with it, and what the
 * viewer may do with it (policies restore and forceDelete). Oldest first.
 *
 * The rows are read per type in one query each, with the relations the
 * policies need set by hand, so the number of queries stays the same
 * however many entries there are.
 *
 * @phpstan-type TrashItem array{deletionId: string, type: string, id: int|string, title: string, project: Project|null, deletedBy: User|null, deletedAt: Carbon, childCount: int, childCounts: list<array{type: string, count: int}>, viewerCanRestore: bool, viewerCanPurge: bool}
 */
final class TrashListing
{
    /**
     * The deletions inside a project (not the project itself).
     *
     * @return list<TrashItem>
     */
    public function inProject(Project $project, User $viewer): array
    {
        $project->loadMissing('members');
        $taskIds = Task::withTrashed()->where('project_id', $project->getKey())->select('id');

        $rows = collect([
            Milestone::onlyTrashed()->where('project_id', $project->getKey())->get(),
            Todo::onlyTrashed()->where('project_id', $project->getKey())->get(),
            Task::onlyTrashed()->where('project_id', $project->getKey())->get(),
            Comment::onlyTrashed()->whereIn('task_id', $taskIds)->get(),
            Document::onlyTrashed()->where('project_id', $project->getKey())->get(),
        ])->flatten(1)->filter(fn (Model $row): bool => $row->deletion_id !== null);

        $this->linkToProject($rows, $project);

        // A document in the trash is seen only by those who manage it (docs/dokumentenverwaltung.md §4.3).
        $access = app(DocumentAccessService::class);
        $rows = $rows->filter(fn (Model $row): bool => ! $row instanceof Document || $access->levelOf($viewer, $row) === DocumentAccess::MANAGE);

        return $this->items($rows, $viewer);
    }

    /**
     * Deleted projects the viewer may bring back.
     *
     * @return list<TrashItem>
     */
    public function deletedProjects(User $viewer): array
    {
        $projects = Project::onlyTrashed()->whereNotNull('deletion_id')->with('members')->get()
            ->filter(fn (Project $project): bool => Gate::forUser($viewer)->allows('restore', $project));

        $ids = $projects->pluck('deletion_id')->all();
        $children = collect([Milestone::class, Todo::class, Task::class, Comment::class, Document::class])
            ->flatMap(fn (string $type) => $type::onlyTrashed()->whereIn('deletion_id', $ids)->get(['id', 'deletion_id'])->all());

        return $this->items($projects->concat($children), $viewer);
    }

    /**
     * The ideas of the viewer in the trash (ideas are private: nobody else sees or restores them).
     *
     * @return list<TrashItem>
     */
    public function ownIdeas(User $viewer): array
    {
        return $this->items(Idea::onlyTrashed()->where('user_id', $viewer->getKey())->whereNotNull('deletion_id')->get(), $viewer);
    }

    /**
     * The entry of one deletion, read before it is restored (afterwards
     * it has no deletion data any more).
     *
     * @return TrashItem
     */
    public function ofDeletion(string $deletionId, User $viewer): array
    {
        $rows = collect(Deletion::TYPES)
            ->flatMap(fn (string $type) => $type::onlyTrashed()->where('deletion_id', $deletionId)->get()->all());

        $top = $rows->first();
        $project = match (true) {
            $top instanceof Project => $top,
            $top instanceof Comment => $top->task->project,
            $top instanceof Idea => null,
            $top !== null => $top->project,
            default => null,
        };

        if ($project !== null) {
            $project->loadMissing('members');
            $this->linkToProject($rows, $project);
        }

        return $this->items($rows, $viewer)[0];
    }

    /**
     * @param  Collection<int, Model>  $rows
     * @return list<TrashItem>
     */
    private function items(Collection $rows, User $viewer): array
    {
        $deletedBy = User::query()->whereKey($rows->pluck('deleted_by')->filter()->unique()->all())->get()->keyBy('id');

        return $rows
            ->groupBy('deletion_id')
            ->map(function (Collection $deletion) use ($viewer, $deletedBy): array {
                $sorted = $deletion->sortBy(fn (Model $row): int => (int) array_search($row::class, Deletion::TYPES, true))->values();
                $top = $sorted->first();
                $others = $sorted->slice(1);

                return [
                    'deletionId' => (string) $top->deletion_id,
                    'type' => strtoupper(Deletion::typeOf($top)),
                    'id' => $top->getKey(),
                    'title' => $this->titleOf($top),
                    'project' => match (true) {
                        $top instanceof Project => $top,
                        $top instanceof Comment => $top->task->project,
                        $top instanceof Idea => null,
                        default => $top->project,
                    },
                    'deletedBy' => $deletedBy->get($top->deleted_by),
                    'deletedAt' => $top->deleted_at,
                    'childCount' => $others->count(),
                    'childCounts' => $others
                        ->countBy(fn (Model $row): string => strtoupper(Deletion::typeOf($row)))
                        ->map(fn (int $count, string $type): array => ['type' => $type, 'count' => $count])
                        ->values()
                        ->all(),
                    'viewerCanRestore' => Gate::forUser($viewer)->allows('restore', $top),
                    'viewerCanPurge' => Gate::forUser($viewer)->allows('forceDelete', $top),
                ];
            })
            ->sortBy([['deletedAt', 'asc'], ['deletionId', 'asc']])
            ->values()
            ->all();
    }

    private function titleOf(Model $top): string
    {
        return match (true) {
            $top instanceof Project => $top->name,
            $top instanceof Comment => RichTextDocument::excerpt($top->body),
            default => (string) $top->title,
        };
    }

    /**
     * Sets the project (and for comments their task) on every row, so the
     * policies read no relation one by one.
     *
     * @param  Collection<int, Model>  $rows
     */
    private function linkToProject(Collection $rows, Project $project): void
    {
        $commentTasks = Task::withTrashed()
            ->whereKey($rows->whereInstanceOf(Comment::class)->pluck('task_id')->unique()->all())
            ->get()
            ->each(fn (Task $task) => $task->setRelation('project', $project))
            ->keyBy('id');

        foreach ($rows as $row) {
            if ($row instanceof Comment) {
                $row->setRelation('task', $commentTasks->get($row->task_id));
            } elseif (! $row instanceof Project) {
                $row->setRelation('project', $project);
            }
        }
    }
}
