<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\GraphQLRunner;
use App\Planer\RichText\TiptapToMarkdown;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_comments')]
#[Description('Lists the comments of one task (task_id) or of the tasks of a project (project_id), per task, the newest last. For a project the filters of export_plan apply (version, milestone_ids, task_status, include_released) and only tasks with comments are listed. Each comment has the author (name, e-mail, isAgent: true for an agent account such as Claude), the time and the text as Markdown. Comments are not part of the plan file: export_plan and import_plan never carry them.')]
#[IsReadOnly]
class ListCommentsTool extends Tool
{
    use RunsGraphQL;

    private const COMMENTS = 'comments { id body createdAt author { name email isAgent } }';

    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'task_id' => ['required_without:project_id'],
                'project_id' => ['required_without:task_id'],
                'version' => ['sometimes', 'nullable', 'string'],
                'milestone_ids' => ['sometimes', 'array'],
                'task_status' => ['sometimes', 'array'],
                'task_status.*' => ['in:idea,planned,open,in_progress,review,done'],
                'include_released' => ['sometimes', 'boolean'],
            ],
            ['task_id.required_without' => 'Give a task_id, or a project_id for the tasks of a project.'],
        );

        return $this->respond(function () use ($validated): Response {
            $runner = app(GraphQLRunner::class);
            $tasks = [];

            if (isset($validated['task_id'])) {
                $id = preg_replace('/^k-/', '', (string) $validated['task_id']);
                $task = $runner->run('query ($id: ID!) { task(id: $id) { id title '.self::COMMENTS.' } }', ['id' => $id])['task'];

                if ($task === null) {
                    return Response::error("k-{$id} is not a task you can see.");
                }

                $tasks[] = $task;
            } else {
                $tasks = $this->tasksOfProject($runner, $validated);
            }

            $listed = [];
            foreach ($tasks as $task) {
                $comments = collect($task['comments'])->sortBy([['createdAt', 'asc'], ['id', 'asc']])->values();

                if ($comments->isEmpty() && ! isset($validated['task_id'])) {
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

            return Response::json(['tasks' => $listed]);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array<string, mixed>>
     */
    private function tasksOfProject(GraphQLRunner $runner, array $validated): array
    {
        $project = $runner->run(
            'query ($id: ID!) { project(id: $id) { milestones { id release { version status } todos { tasks { id title status '.self::COMMENTS.' } } } } }',
            ['id' => (string) $validated['project_id']],
        )['project'];

        $ids = array_map(fn ($id): string => preg_replace('/^m-/', '', (string) $id), $validated['milestone_ids'] ?? []);
        $statuses = array_map('strtoupper', $validated['task_status'] ?? []);
        $tasks = [];

        foreach ($project['milestones'] ?? [] as $milestone) {
            $release = $milestone['release'];

            if (isset($validated['version']) && ($release['version'] ?? null) !== $validated['version']) {
                continue;
            }

            if ($ids !== [] && ! in_array((string) $milestone['id'], $ids, true)) {
                continue;
            }

            if (! ($validated['include_released'] ?? true) && ($release['status'] ?? null) === 'RELEASED') {
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

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->description('Id of one task, k-<id> or the number.'),
            'project_id' => $schema->string()->description('Id of a project: the comments of its tasks.'),
            'version' => $schema->string()->description('With project_id: only the milestone of this release version.'),
            'milestone_ids' => $schema->array()->items($schema->string())->description('With project_id: only these milestones.'),
            'task_status' => $schema->array()->items($schema->string()->enum(['idea', 'planned', 'open', 'in_progress', 'review', 'done']))->description('With project_id: only tasks with these statuses.'),
            'include_released' => $schema->boolean()->description('With project_id: false leaves out released milestones.'),
        ];
    }
}
