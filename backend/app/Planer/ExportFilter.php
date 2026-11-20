<?php

namespace App\Planer;

use App\Enums\ReleaseStatus;
use App\Models\Milestone;
use App\Models\Todo;
use Illuminate\Database\Eloquent\Collection;

/**
 * The filter of a partial export (excerpt). Milestone level: a version, a list of milestone ids, and whether released
 * versions are in. Task level: a list of task statuses; the tasks of other statuses leave, and a todo or a milestone
 * left without a task goes with them. A filter without any condition is not active: the export is the whole plan.
 */
final class ExportFilter
{
    /**
     * @param  list<int>  $milestoneIds  Numbers of the milestones (the part after "m-")
     * @param  list<string>  $taskStatus  Values of TaskStatus
     */
    public function __construct(
        public readonly ?string $version = null,
        public readonly array $milestoneIds = [],
        public readonly array $taskStatus = [],
        public readonly bool $includeReleased = true,
    ) {}

    public function active(): bool
    {
        return $this->version !== null || $this->milestoneIds !== [] || $this->taskStatus !== [] || ! $this->includeReleased;
    }

    /**
     * @param  Collection<int, Milestone>  $milestones  With todos and their tasks loaded
     * @return Collection<int, Milestone>
     */
    public function apply(Collection $milestones): Collection
    {
        return new Collection($milestones
            ->filter(fn (Milestone $milestone): bool => $this->milestoneMatches($milestone))
            ->each(function (Milestone $milestone): void {
                if ($this->taskStatus === []) {
                    return;
                }

                $milestone->setRelation('todos', $milestone->todos
                    ->each(fn (Todo $todo) => $todo->setRelation('tasks', $todo->tasks->filter(fn ($task): bool => in_array($task->getAttribute('status')->value, $this->taskStatus, true))->values()))
                    ->filter(fn (Todo $todo): bool => $todo->tasks->isNotEmpty())
                    ->values());
            })
            ->filter(fn (Milestone $milestone): bool => $this->taskStatus === [] || $milestone->todos->isNotEmpty())
            ->values()
            ->all());
    }

    private function milestoneMatches(Milestone $milestone): bool
    {
        if ($this->version !== null && $milestone->release_version !== $this->version) {
            return false;
        }

        if ($this->milestoneIds !== [] && ! in_array((int) $milestone->getKey(), $this->milestoneIds, true)) {
            return false;
        }

        return $this->includeReleased || $milestone->getAttribute('release_status') !== ReleaseStatus::RELEASED;
    }
}
