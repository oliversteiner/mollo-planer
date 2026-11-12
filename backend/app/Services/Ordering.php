<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The order of rows inside a group: the milestones of a project, the tasks of
 * a Kanban column. Positions start at 0 and have no gaps after a change.
 *
 * Every method locks the project row first, so two changes in the same project
 * run one after the other. The callers run inside a transaction (Lighthouse
 * runs each mutation in one), otherwise the lock would end at once.
 *
 * A group is passed as a query builder. The query is cloned, so the caller's
 * builder is never changed.
 */
class Ordering
{
    public function lockProject(int|string $projectId): void
    {
        Project::query()->whereKey($projectId)->lockForUpdate()->first();
    }

    /**
     * Position for a row at the end of the group (0 for an empty group).
     *
     * @param  Builder<Model>  $group
     */
    public function nextPosition(int|string $projectId, Builder $group): int
    {
        $this->lockProject($projectId);

        $max = (clone $group)->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }

    /**
     * Makes room at the given position: the rows of the group from there on move one place back.
     *
     * @param  Builder<Model>  $group
     */
    public function openSlot(int|string $projectId, Builder $group, int $position): void
    {
        $this->lockProject($projectId);

        (clone $group)->where('position', '>=', $position)->increment('position');
    }

    /**
     * Puts the item into the target group at the given place (clamped to the end)
     * and renumbers the group from 0. If the item leaves a source group, that
     * group is renumbered too. The item is saved, so its observers run.
     *
     * @param  Builder<Model>  $target  The group the item ends up in, may contain the item itself
     * @param  Builder<Model>|null  $source  The group the item comes from, if it changes group
     */
    public function move(Model $item, int|string $projectId, Builder $target, int $position, ?Builder $source = null): void
    {
        $this->lockProject($projectId);

        $ordered = $this->rowsOf((clone $target)->whereKeyNot($item->getKey()));
        $position = max(0, min($position, count($ordered)));
        array_splice($ordered, $position, 0, [$item]);

        $this->renumber($ordered);

        if ($source !== null) {
            $this->renumber($this->rowsOf((clone $source)->whereKeyNot($item->getKey())));
        }
    }

    /**
     * Renumbers the group from 0, for example after a row was deleted.
     *
     * @param  Builder<Model>  $group
     */
    public function close(int|string $projectId, Builder $group): void
    {
        $this->lockProject($projectId);

        $this->renumber($this->rowsOf(clone $group));
    }

    /**
     * @param  Builder<Model>  $group
     * @return list<Model>
     */
    private function rowsOf(Builder $group): array
    {
        return $group->orderBy('position')->orderBy('id')->get()->all();
    }

    /**
     * Saves positions 0..n. A row whose position did not change is not saved.
     *
     * @param  list<Model>  $rows
     */
    private function renumber(array $rows): void
    {
        foreach ($rows as $index => $row) {
            $row->position = $index;

            if ($row->isDirty()) {
                $row->save();
            }
        }
    }
}
