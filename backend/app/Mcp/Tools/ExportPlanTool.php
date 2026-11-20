<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\GraphQLRunner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('export_plan')]
#[Description('Exports the whole plan of a project as a planer-v2 document: project, members, labels, milestones, their todos and the tasks of each todo, with the revision. Use it before any change, so you work on the current state. The ids (m-<id>, t-<id>, k-<id>) are the ones of the app. With a filter (version, milestone_ids, task_status, include_released) you get an excerpt: a valid document marked excerpt: true. Write it back only with import_plan mode partial; mode full refuses it.')]
#[IsReadOnly]
class ExportPlanTool extends Tool
{
    use RunsGraphQL;

    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'project_id' => ['required'],
                'version' => ['sometimes', 'nullable', 'string'],
                'milestone_ids' => ['sometimes', 'array'],
                'task_status' => ['sometimes', 'array'],
                'task_status.*' => ['in:idea,planned,open,in_progress,review,done'],
                'include_released' => ['sometimes', 'boolean'],
            ],
            ['project_id.required' => 'Give the project_id, for example from list_projects.'],
        );

        return $this->respond(function () use ($validated): Response {
            $data = app(GraphQLRunner::class)->run(
                'query ($id: ID!, $version: String, $ids: [ID!], $status: [TaskStatus!], $released: Boolean!) { exportPlan(projectId: $id, version: $version, milestoneIds: $ids, taskStatus: $status, includeReleased: $released) }',
                [
                    'id' => (string) $validated['project_id'],
                    'version' => isset($validated['version']) ? (string) $validated['version'] : null,
                    'ids' => array_map('strval', $validated['milestone_ids'] ?? []) ?: null,
                    'status' => array_map('strtoupper', $validated['task_status'] ?? []) ?: null,
                    'released' => (bool) ($validated['include_released'] ?? true),
                ],
            );

            return Response::json(['document' => json_decode((string) $data['exportPlan'], true, 512, JSON_THROW_ON_ERROR)]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Id of the project (from list_projects).')->required(),
            'version' => $schema->string()->description('Only the milestone of this release version, for example "1.21".'),
            'milestone_ids' => $schema->array()->items($schema->string())->description('Only these milestones (m-<id> or the number).'),
            'task_status' => $schema->array()->items($schema->string()->enum(['idea', 'planned', 'open', 'in_progress', 'review', 'done']))->description('Only tasks with these statuses; todos and milestones without such a task are left out.'),
            'include_released' => $schema->boolean()->description('false: leave out released milestones. Default true.'),
        ];
    }
}
