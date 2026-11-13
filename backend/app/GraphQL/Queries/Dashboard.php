<?php

namespace App\GraphQL\Queries;

use App\Enums\ItemStatus;
use App\Enums\TaskStatus;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Task;
use App\Models\Todo;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Query.dashboard: the start page in one request. The fields resolve
 * themselves; the root value carries nothing.
 */
class Dashboard
{
    use ResolvesViewer;

    /**
     * @return array<string, never>
     */
    public function __invoke(): array
    {
        return [];
    }

    /**
     * Open todos the viewer may see, by due date (without date last).
     *
     * @param  array{limit: int}  $args
     * @return Collection<int, Todo>
     */
    public function upcomingTodos(mixed $root, array $args, GraphQLContext $context): Collection
    {
        return Todo::query()
            ->visibleTo($this->viewer($context))
            ->where('status', ItemStatus::OPEN->value)
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit($args['limit'])
            ->get();
    }

    /**
     * The viewer's tasks that are not done yet, by due date.
     *
     * @param  array<string, mixed>  $args
     * @return Collection<int, Task>
     */
    public function myOpenTasks(mixed $root, array $args, GraphQLContext $context): Collection
    {
        $viewer = $this->viewer($context);

        return Task::query()
            ->assignedTo($viewer)
            ->visibleTo($viewer)
            ->whereNotIn('status', [TaskStatus::DONE->value, TaskStatus::IDEA->value])
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }
}
