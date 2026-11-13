<?php

namespace App\GraphQL\Resolvers;

use App\Enums\TaskStatus;
use App\Models\Task;
use Closure;
use GraphQL\Deferred;
use Illuminate\Database\Eloquent\Builder;

/**
 * Collects the keys (todo, milestone or project ids) of one GraphQL field
 * and counts the tasks of all of them in a single grouped query.
 */
final class ProgressBatchLoader
{
    /**
     * Keys requested but not counted yet.
     *
     * @var array<int|string, true>
     */
    private array $pending = [];

    /**
     * @var array<int|string, array{total: int, done: int, percent: int}>
     */
    private array $results = [];

    /**
     * @param  Closure(list<int|string>): Builder<Task>  $tasksByKey  Tasks of the given keys, with the key selected as "group_key"
     * @param  string  $groupColumn  Qualified column the tasks are grouped by
     */
    public function __construct(
        private readonly Closure $tasksByKey,
        private readonly string $groupColumn,
    ) {}

    public function load(int|string $key): Deferred
    {
        if (! array_key_exists($key, $this->results)) {
            $this->pending[$key] = true;
        }

        return new Deferred(function () use ($key): array {
            $this->countPending();

            return $this->results[$key] ?? self::progress(0, 0);
        });
    }

    /**
     * @return array{total: int, done: int, percent: int}
     */
    public static function progress(int $total, int $done): array
    {
        return [
            'total' => $total,
            'done' => $done,
            'percent' => $total === 0 ? 0 : (int) round($done * 100 / $total),
        ];
    }

    private function countPending(): void
    {
        if ($this->pending === []) {
            return;
        }

        $keys = array_keys($this->pending);
        $this->pending = [];

        ($this->tasksByKey)($keys)
            ->toBase()
            ->selectRaw('sum(case when tasks.status = ? then 0 else 1 end) as total', [TaskStatus::IDEA->value])
            ->selectRaw('sum(case when tasks.status = ? then 1 else 0 end) as done', [TaskStatus::DONE->value])
            ->groupBy($this->groupColumn)
            ->get()
            ->each(function (object $row): void {
                $this->results[$row->group_key] = self::progress((int) $row->total, (int) $row->done);
            });

        foreach ($keys as $key) {
            $this->results[$key] ??= self::progress(0, 0);
        }
    }
}
