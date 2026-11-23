<?php

namespace App\GraphQL\Resolvers;

use App\Models\Milestone;
use App\Models\Task;
use App\Models\Todo;
use GraphQL\Deferred;

/**
 * Collects the project ids of one GraphQL field and counts milestones, todos and tasks of all of them in three
 * grouped queries (the cards of the project list, task 33 D): the number of queries does not grow with the number of
 * projects. Elements in the trash do not count; all statuses of a task count, ideas included.
 */
final class ProjectCountsBatchLoader
{
    /** @var array<int|string, true> */
    private array $pending = [];

    /** @var array<int|string, array{milestones: int, todos: int, tasks: int}> */
    private array $results = [];

    public function load(int|string $key): Deferred
    {
        if (! array_key_exists($key, $this->results)) {
            $this->pending[$key] = true;
        }

        return new Deferred(function () use ($key): array {
            $this->countPending();

            return $this->results[$key] ?? ['milestones' => 0, 'todos' => 0, 'tasks' => 0];
        });
    }

    private function countPending(): void
    {
        if ($this->pending === []) {
            return;
        }

        $keys = array_keys($this->pending);
        $this->pending = [];

        $counts = [];
        foreach (['milestones' => Milestone::class, 'todos' => Todo::class, 'tasks' => Task::class] as $name => $model) {
            $counts[$name] = $model::query()->whereIn('project_id', $keys)->toBase()
                ->selectRaw('project_id, count(*) as total')->groupBy('project_id')->pluck('total', 'project_id');
        }

        foreach ($keys as $key) {
            $this->results[$key] = [
                'milestones' => (int) ($counts['milestones'][$key] ?? 0),
                'todos' => (int) ($counts['todos'][$key] ?? 0),
                'tasks' => (int) ($counts['tasks'][$key] ?? 0),
            ];
        }
    }
}
