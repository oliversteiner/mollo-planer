<?php

namespace App\Services;

use App\Enums\Right;
use App\GraphQL\Errors\OrderRefused;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Puts the siblings of one level in a new order, all at once (task 47): the milestones of a project, the todos of
 * a milestone or the tasks of a todo. The list must hold every sibling exactly once; nothing moves to another
 * parent (that stays with the import, mode partial). Used by the MCP tool "reorder" and by "Rückgängig" in the
 * interface.
 */
class SiblingOrder
{
    public const MILESTONES = 'MILESTONES';

    public const TODOS = 'TODOS';

    public const TASKS = 'TASKS';

    public function __construct(private readonly Ordering $ordering) {}

    /**
     * @param  list<int|string>  $ids  every sibling, in the new order
     * @return array{revision: int, moveCount: int, before: list<string>}
     *
     * @throws OrderRefused when the list is not complete or the plan changed
     * @throws AuthorizationException when the person may not change this order
     */
    public function apply(User $actor, Project $project, string $level, int|string|null $parentId, array $ids, ?int $expectedRevision, bool $dryRun = false): array
    {
        return DB::transaction(function () use ($actor, $project, $level, $parentId, $ids, $expectedRevision, $dryRun): array {
            $this->ordering->lockProject($project->getKey());
            $fresh = Project::query()->findOrFail($project->getKey());

            [$group, $right] = $this->group($fresh, $level, $parentId);

            if (! Rights::allows($actor, $right, $fresh) || $fresh->isArchived()) {
                throw new AuthorizationException($fresh->isArchived() ? __('projects.archived') : __('ordering.forbidden'));
            }

            if ($expectedRevision !== null && $expectedRevision !== (int) $fresh->planer_revision) {
                throw OrderRefused::revision($expectedRevision, (int) $fresh->planer_revision);
            }

            $current = (clone $group)->orderBy('position')->orderBy('id')->pluck('id')->map(fn ($id): string => (string) $id)->all();
            $given = array_map('strval', $ids);

            $missing = array_values(array_diff($current, $given));
            $foreign = array_values(array_unique(array_diff($given, $current)));

            if ($missing !== [] || $foreign !== [] || count($given) !== count(array_unique($given))) {
                throw OrderRefused::siblings(
                    array_map(fn (string $id): string => $this->prefix($level).$id, $missing),
                    array_map(fn (string $id): string => $this->prefix($level).$id, $foreign),
                );
            }

            $moves = 0;
            foreach ($given as $index => $id) {
                $moves += $current[$index] !== $id ? 1 : 0;
            }

            if (! $dryRun && $moves > 0) {
                $this->ordering->arrange($group, $given);
            }

            return [
                'revision' => (int) Project::query()->whereKey($fresh->getKey())->value('planer_revision'),
                'moveCount' => $moves,
                'before' => $current,
            ];
        });
    }

    /**
     * @return array{0: Builder, 1: Right}
     */
    private function group(Project $project, string $level, int|string|null $parentId): array
    {
        if ($level === self::MILESTONES) {
            return [Milestone::query()->where('project_id', $project->getKey()), Right::MILESTONE_UPDATE];
        }

        if ($parentId === null) {
            throw OrderRefused::siblings([], ['parent_id']);
        }

        if ($level === self::TODOS) {
            Milestone::query()->where('project_id', $project->getKey())->findOrFail($parentId);

            return [Todo::query()->where('milestone_id', $parentId), Right::TODO_UPDATE];
        }

        Todo::query()->whereIn('milestone_id', Milestone::query()->where('project_id', $project->getKey())->select('id'))->findOrFail($parentId);

        return [Task::query()->where('todo_id', $parentId), Right::TASK_MOVE];
    }

    private function prefix(string $level): string
    {
        return match ($level) {
            self::MILESTONES => 'm-',
            self::TODOS => 't-',
            default => 'k-',
        };
    }
}
