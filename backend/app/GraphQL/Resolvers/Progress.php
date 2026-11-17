<?php

namespace App\GraphQL\Resolvers;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\Todo;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Execution\BatchLoader\BatchLoaderRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Progress of a project, milestone or todo: done tasks out of all tasks.
 * Computed on request, never stored. All objects of one list are counted
 * together in one grouped query (ProgressBatchLoader), not one each.
 *
 * The queries start from tasks and reach their todo only through Eloquent
 * (no join), so the scopes of the models hold for both, the scope of
 * SoftDeletes included (docs/refactoring-m1-m3.md RF6).
 */
class Progress
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function project(Project $project, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Deferred
    {
        return $this->loader($resolveInfo, 'tasks.project_id', fn (array $projectIds) => Task::query()
            ->whereIn('tasks.project_id', $projectIds)
            ->select('tasks.project_id as group_key'),
        )->load($project->getKey());
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function milestone(Milestone $milestone, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Deferred
    {
        return $this->loader($resolveInfo, 'group_key', fn (array $milestoneIds) => Task::query()
            ->whereIn('tasks.todo_id', Todo::query()->select('todos.id')->whereIn('todos.milestone_id', $milestoneIds))
            ->select(['group_key' => Todo::query()->select('todos.milestone_id')->whereColumn('todos.id', 'tasks.todo_id')]),
        )->load($milestone->getKey());
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function todo(Todo $todo, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Deferred
    {
        return $this->loader($resolveInfo, 'tasks.todo_id', fn (array $todoIds) => Task::query()
            ->whereIn('tasks.todo_id', $todoIds)
            ->select('tasks.todo_id as group_key'),
        )->load($todo->getKey());
    }

    /**
     * One loader per field path, so every todo in a list shares it.
     *
     * @param  \Closure(list<int|string>): Builder<Task>  $tasksByKey
     */
    private function loader(ResolveInfo $resolveInfo, string $groupColumn, \Closure $tasksByKey): ProgressBatchLoader
    {
        return BatchLoaderRegistry::instance(
            $resolveInfo->path,
            fn (): ProgressBatchLoader => new ProgressBatchLoader($tasksByKey, $groupColumn),
        );
    }
}
