<?php

namespace App\GraphQL\Resolvers;

use App\Enums\Right;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Models\Task;
use App\Support\Rights;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Task.viewerCanUpdate / viewerCanAssign / viewerCanAssignSelf /
 * viewerCanDelete, answered by the TaskPolicy and Rights::mayAssignTask.
 * "task.updateOwn" holds per task (created_by), which Project.viewerRights
 * cannot say (docs/fix-m1-f4.md F4.5).
 *
 * A board lists many tasks of one project: the project is read once per
 * request, with its members, so the rights cost no query per task.
 */
class TaskViewerCan
{
    use ResolvesViewer;

    private const CACHE_KEY = 'taskViewerCan.projects';

    /**
     * @param  array<string, mixed>  $args
     */
    public function update(Task $task, array $args, GraphQLContext $context): bool
    {
        return $this->viewer($context)->can('update', $this->withProject($task, $context));
    }

    /**
     * May assign the task to anyone (with only "task.assignSelf": no, see
     * viewerCanAssignSelf).
     *
     * @param  array<string, mixed>  $args
     */
    public function assign(Task $task, array $args, GraphQLContext $context): bool
    {
        return Rights::allows($this->viewer($context), Right::TASK_ASSIGN, $this->withProject($task, $context)->project);
    }

    /**
     * May at least assign the task to themselves (or take themselves off it).
     *
     * @param  array<string, mixed>  $args
     */
    public function assignSelf(Task $task, array $args, GraphQLContext $context): bool
    {
        return $this->viewer($context)->can('assign', $this->withProject($task, $context));
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function delete(Task $task, array $args, GraphQLContext $context): bool
    {
        return $this->viewer($context)->can('delete', $this->withProject($task, $context));
    }

    /** The task with its project set, the project with its members loaded. */
    private function withProject(Task $task, GraphQLContext $context): Task
    {
        $project = $this->project($task, $context);

        if ($project !== null) {
            $task->setRelation('project', $project);
        }

        return $task;
    }

    private function project(Task $task, GraphQLContext $context): ?Project
    {
        $id = (int) $task->project_id;
        $attributes = $context->request()?->attributes;
        /** @var array<int, Project|null> $projects */
        $projects = $attributes?->get(self::CACHE_KEY, []) ?? [];

        if (! array_key_exists($id, $projects)) {
            $projects[$id] = $task->relationLoaded('project') && $task->project !== null
                ? $task->project->loadMissing('members')
                : Project::query()->with('members')->find($id);
            $attributes?->set(self::CACHE_KEY, $projects);
        }

        return $projects[$id];
    }
}
