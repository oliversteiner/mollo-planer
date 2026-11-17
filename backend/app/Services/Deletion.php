<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Planer\PlanerLimits;
use App\Planer\PlanerRevision;
use App\Support\AppSettings;
use App\Support\Rights;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The trash (docs/auftrag-m2.md §2), the one place where project elements are
 * deleted, restored and deleted for good.
 *
 * A deletion is one "Vorgang": the element and every child not in the trash
 * yet get the same deletion_id (milestone → todos → tasks → comments, project
 * → everything). Children deleted on their own before keep their own id and
 * stay in the trash when the parent comes back. The observers of the element
 * close gaps, update statuses and count the planer revision once; the
 * children are written in bulk, without events.
 *
 * Who may do it is checked before (policies restore and forceDelete).
 */
final class Deletion
{
    /**
     * From the top down: the first type that holds a deletion_id is the
     * element the deletion started with.
     *
     * @var list<class-string<Model>>
     */
    public const TYPES = [Project::class, Milestone::class, Todo::class, Task::class, Comment::class];

    public function __construct(
        private readonly KanbanOrdering $kanban,
        private readonly Ordering $ordering,
    ) {}

    /** Into the trash, with every child that is not there yet. */
    public function delete(Model $model): Model
    {
        return DB::transaction(function () use ($model): Model {
            $deletionId = (string) Str::uuid();
            $deletedBy = Auth::id();

            foreach ($this->activeChildren($model) as $children) {
                $children()->update(['deleted_at' => now(), 'deleted_by' => $deletedBy, 'deletion_id' => $deletionId]);
            }

            $model->forceFill(['deleted_by' => $deletedBy, 'deletion_id' => $deletionId])->saveQuietly();
            $model->delete();

            if ($model instanceof Milestone || $model instanceof Todo) {
                $this->kanban->close($model->project_id);
            }

            return $model;
        });
    }

    /**
     * Back from the trash: exactly this deletion. Refused while a parent is
     * in the trash, naming what has to come back first. The element goes to
     * the end of its list, its tasks to the end of their columns. A project
     * without anybody who may delete it any more gets the restoring person
     * as a member with the creator role (ground rule §7.3).
     *
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function restore(string $deletionId, User $actor): Model
    {
        return DB::transaction(function () use ($deletionId, $actor): Model {
            $top = $this->topOf($deletionId);
            $this->assertParentIsBack($top);
            $this->assertRoom($top);

            $taskIds = $top instanceof Task ? [] : Task::onlyTrashed()->where('deletion_id', $deletionId)->pluck('id')->all();

            foreach ($this->typesBelow($top) as $type) {
                $type::onlyTrashed()->where('deletion_id', $deletionId)->update(['deleted_at' => null, 'deleted_by' => null, 'deletion_id' => null]);
            }

            if ($taskIds !== [] && ! $top instanceof Project) {
                $this->kanban->append($top->project_id, Task::query()->whereKey($taskIds)->get());
            }

            if ($top instanceof Milestone) {
                $this->ordering->close($top->project_id, Todo::query()->where('milestone_id', $top->getKey()));
            }

            $top->forceFill(['deleted_by' => null, 'deletion_id' => null]);
            $top->restore();

            if ($top instanceof Project) {
                $this->keepHolder($top, $actor);
            }

            return $top;
        });
    }

    /**
     * For good, through the cascades of the database. Children of other
     * deletions inside go too. The planer file does not change.
     *
     * @throws ModelNotFoundException
     */
    public function purge(string $deletionId): void
    {
        DB::transaction(fn () => $this->topOf($deletionId)->forceDelete());
    }

    /**
     * Every deletion inside the project, for good (not the project itself).
     * Returns how many there were.
     */
    public function emptyProjectTrash(Project $project): int
    {
        return DB::transaction(function () use ($project): int {
            $deletionIds = array_values(array_filter(
                $this->deletionIdsIn($project),
                fn (string $id): bool => ! $this->findTop($id) instanceof Project,
            ));

            // A deletion inside another one may already be gone with it (cascade).
            foreach ($deletionIds as $deletionId) {
                $this->findTop($deletionId)?->forceDelete();
            }

            return count($deletionIds);
        });
    }

    /**
     * The element a deletion started with.
     *
     * @throws ModelNotFoundException
     */
    public function topOf(string $deletionId): Model
    {
        return $this->findTop($deletionId) ?? throw (new ModelNotFoundException)->setModel(Model::class, [$deletionId]);
    }

    public function findTop(string $deletionId): ?Model
    {
        foreach (self::TYPES as $type) {
            $model = $type::onlyTrashed()->where('deletion_id', $deletionId)->first();

            if ($model !== null) {
                return $model;
            }
        }

        return null;
    }

    /**
     * The deletion ids of the project's milestones, todos, tasks and
     * comments in the trash, oldest first.
     *
     * @return list<string>
     */
    public function deletionIdsIn(Project $project): array
    {
        $tasksOfProject = Task::withTrashed()->where('project_id', $project->getKey())->select('id');

        return collect([
            Milestone::onlyTrashed()->where('project_id', $project->getKey()),
            Todo::onlyTrashed()->where('project_id', $project->getKey()),
            Task::onlyTrashed()->where('project_id', $project->getKey()),
            Comment::onlyTrashed()->whereIn('task_id', $tasksOfProject),
        ])
            ->flatMap(fn (Builder $trashed) => $trashed->whereNotNull('deletion_id')->get(['deletion_id', 'deleted_at'])->all())
            ->sortBy('deleted_at')
            ->pluck('deletion_id')
            ->unique()
            ->values()
            ->all();
    }

    /** Short name of the type for texts and GraphQL ("milestone"). */
    public static function typeOf(Model $model): string
    {
        return Str::lower(class_basename($model));
    }

    /**
     * Queries of the children not in the trash, deepest first: each runs
     * while its parents are still there to find them.
     *
     * @return list<\Closure(): Builder<Model>>
     */
    private function activeChildren(Model $model): array
    {
        $tasks = match (true) {
            $model instanceof Project => fn () => Task::query()->where('project_id', $model->getKey()),
            $model instanceof Milestone => fn () => Task::query()->whereIn('todo_id', Todo::query()->where('milestone_id', $model->getKey())->select('id')),
            $model instanceof Todo => fn () => Task::query()->where('todo_id', $model->getKey()),
            default => null,
        };

        $children = [];

        if ($model instanceof Task) {
            $children[] = fn () => Comment::query()->where('task_id', $model->getKey());
        }

        if ($tasks !== null) {
            $children[] = fn () => Comment::query()->whereIn('task_id', $tasks()->select('id'));
            $children[] = $tasks;
        }

        if ($model instanceof Project) {
            $children[] = fn () => Todo::query()->where('project_id', $model->getKey());
            $children[] = fn () => Milestone::query()->where('project_id', $model->getKey());
        }

        if ($model instanceof Milestone) {
            $children[] = fn () => Todo::query()->where('milestone_id', $model->getKey());
        }

        return $children;
    }

    /**
     * @return list<class-string<Model>>
     */
    private function typesBelow(Model $top): array
    {
        return array_slice(self::TYPES, array_search($top::class, self::TYPES, true) + 1);
    }

    /**
     * @throws ValidationException
     */
    /**
     * Coming back must not break the limits of the planer file. Only the top
     * counts: what is below it was inside the limits when it went.
     *
     * @throws ValidationException
     */
    private function assertRoom(Model $top): void
    {
        match (true) {
            $top instanceof Milestone => PlanerLimits::assertMilestoneRoom($top->project_id),
            $top instanceof Todo => PlanerLimits::assertTodoRoom($top->milestone_id),
            $top instanceof Task => PlanerLimits::assertTaskRoom($top->todo_id),
            default => null,
        };
    }

    private function assertParentIsBack(Model $top): void
    {
        $parent = match (true) {
            $top instanceof Milestone => $top->project,
            $top instanceof Todo => $top->milestone,
            $top instanceof Task => $top->todo,
            $top instanceof Comment => $top->task,
            default => null,
        };

        if ($parent === null || ! $parent->trashed()) {
            return;
        }

        $first = $parent->deletion_id === null ? $parent : ($this->findTop($parent->deletion_id) ?? $parent);
        $title = $first instanceof Project ? $first->name : $first->title;

        throw ValidationException::withMessages([
            'deletionId' => __('trash.parent_deleted', ['type' => __('trash.types.'.self::typeOf($first)), 'title' => $title]),
        ]);
    }

    private function keepHolder(Project $project, User $actor): void
    {
        if (Rights::countHolders($project) > 0) {
            return;
        }

        $role = AppSettings::projectCreatorRole();

        if ($project->roleIdOf($actor) === null) {
            $project->members()->attach($actor->getKey(), ['role_id' => $role->id]);
        } else {
            $project->members()->updateExistingPivot($actor->getKey(), ['role_id' => $role->id]);
        }

        $project->unsetRelation('members');
        app(PlanerRevision::class)->touch($project->getKey());
    }
}
