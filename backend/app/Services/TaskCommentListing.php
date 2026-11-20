<?php

namespace App\Services;

use App\Models\User;
use App\Planer\RichText\TiptapToMarkdown;
use App\Tools\Support\GraphQLRunner;

/**
 * The comments of one task, or of the tasks of a project with the filters of export_plan (version, milestones,
 * status, released), as the tool list_comments gives them (task 34). It reads through GraphQL as the person, so
 * the rights of the app hold; the filtering of a project's tasks is the only logic of its own.
 */
class TaskCommentListing
{
    private const COMMENTS = 'comments { id body createdAt author { name email isAgent } }';

    /**
     * @param  array<string, mixed>  $filters  task_id, or project_id with version, milestone_ids, task_status, include_released
     * @return list<array<string, mixed>>
     *
     * @throws TaskCommentListingRefused
     */
    public function list(User $actor, array $filters): array
    {
        $runner = app(GraphQLRunner::class);

        if (isset($filters['task_id'])) {
            $id = preg_replace('/^k-/', '', (string) $filters['task_id']);
            $task = $runner->run('query ($id: ID!) { task(id: $id) { id title '.self::COMMENTS.' } }', ['id' => $id], $actor)['task'];

            if ($task === null) {
                throw new TaskCommentListingRefused("k-{$id} is not a task you can see.");
            }

            $tasks = [$task];
        } else {
            $tasks = $this->tasksOfProject($runner, $actor, $filters);
        }

        $listed = [];
        foreach ($tasks as $task) {
            $comments = collect($task['comments'])->sortBy([['createdAt', 'asc'], ['id', 'asc']])->values();

            if ($comments->isEmpty() && ! isset($filters['task_id'])) {
                continue;
            }

            $listed[] = [
                'task_id' => "k-{$task['id']}",
                'title' => $task['title'],
                'comments' => $comments->map(fn (array $comment): array => [
                    'id' => $comment['id'],
                    'author' => $comment['author'],
                    'created_at' => $comment['createdAt'],
                    'text' => TiptapToMarkdown::convert($comment['body']),
                ])->all(),
            ];
        }

        return $listed;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function tasksOfProject(GraphQLRunner $runner, User $actor, array $filters): array
    {
        $project = $runner->run(
            'query ($id: ID!) { project(id: $id) { milestones { id release { version status } todos { tasks { id title status '.self::COMMENTS.' } } } } }',
            ['id' => (string) $filters['project_id']],
            $actor,
        )['project'];

        $ids = array_map(fn ($id): string => preg_replace('/^m-/', '', (string) $id), $filters['milestone_ids'] ?? []);
        $statuses = array_map('strtoupper', $filters['task_status'] ?? []);
        $tasks = [];

        foreach ($project['milestones'] ?? [] as $milestone) {
            $release = $milestone['release'];

            if (isset($filters['version']) && ($release['version'] ?? null) !== $filters['version']) {
                continue;
            }

            if ($ids !== [] && ! in_array((string) $milestone['id'], $ids, true)) {
                continue;
            }

            if (! ($filters['include_released'] ?? true) && ($release['status'] ?? null) === 'RELEASED') {
                continue;
            }

            foreach ($milestone['todos'] as $todo) {
                foreach ($todo['tasks'] as $task) {
                    if ($statuses === [] || in_array($task['status'], $statuses, true)) {
                        $tasks[] = $task;
                    }
                }
            }
        }

        return $tasks;
    }
}
