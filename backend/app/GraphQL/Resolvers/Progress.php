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
 */
class Progress
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function project(Project $project, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Deferred
    {
        return $this->loader($resolveInfo, 'milestones.project_id', fn (array $projectIds) => Task::query()
            ->join('todos', 'todos.id', '=', 'tasks.todo_id')
            ->join('milestones', 'milestones.id', '=', 'todos.milestone_id')
            ->whereIn('milestones.project_id', $projectIds)
            ->select('milestones.project_id as group_key'),
        )->load($project->getKey());
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function milestone(Milestone $milestone, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): Deferred
    {
        return $this->loader($resolveInfo, 'todos.milestone_id', fn (array $milestoneIds) => Task::query()
            ->join('todos', 'todos.id', '=', 'tasks.todo_id')
            ->whereIn('todos.milestone_id', $milestoneIds)
            ->select('todos.milestone_id as group_key'),
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
