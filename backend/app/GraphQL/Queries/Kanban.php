<?php

namespace App\GraphQL\Queries;

use App\Enums\TaskStatus;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Query.kanban: one column per task status, tasks sorted by position.
 * A project the viewer may not see gives an empty board.
 */
class Kanban
{
    use ResolvesViewer;

    /**
     * @param  array{projectId: int|string}  $args
     * @return list<array{status: TaskStatus, tasks: Collection<int, Task>}>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        $project = Project::query()->visibleTo($this->viewer($context))->find($args['projectId']);

        if ($project === null) {
            return [];
        }

        $tasks = Task::query()
            ->inProject($project->getKey())
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Task $task) => $task->status->value);

        return array_map(
            fn (TaskStatus $status) => [
                'status' => $status,
                'tasks' => $tasks->get($status->value, new Collection),
            ],
            TaskStatus::cases(),
        );
    }
}
