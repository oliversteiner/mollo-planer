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
#[Description('Exports a project as planer-v2 JSON (with revision and m-/t-/k- IDs). Prefer filters (version, milestone_ids, task_status, include_released: false) on large plans -> returns excerpt: true, write back only with import_plan mode "partial".')]
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
            ['project_id.required' => 'Give project_id (from list_projects).'],
        );

        return $this->respond(function () use ($validated): Response {
            $data = app(GraphQLRunner::class)->run(
                'query ($id: ID!, $version: String, $ids: [ID!], $status: [TaskStatus!], $released: Boolean!) { exportPlan(projectId: $id, version: $version, milestoneIds: $ids, taskStatus: $status, includeReleased: $released) }',
                [
                    'id' => (string) $validated['project_id'],
                    'version' => isset($validated['version']) ? (string) $validated['version'] : null,
                    'ids' => array_map(fn ($id): string => preg_replace('/^m-/', '', (string) $id), $validated['milestone_ids'] ?? []) ?: null,
                    'status' => array_map('strtoupper', $validated['task_status'] ?? []) ?: null,
                    'released' => (bool) ($validated['include_released'] ?? true),
                ],
            );

            $decoded = json_decode((string) $data['exportPlan'], true, 512, JSON_THROW_ON_ERROR);

            return Response::text(json_encode(
                ['document' => $decoded],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ID (from list_projects).')->required(),
            'version' => $schema->string()->description('Filter to release version, e.g. "1.21".'),
            'milestone_ids' => $schema->array()->items($schema->string())->description('Filter to milestone IDs (m-<id> or number).'),
            'task_status' => $schema->array()->items($schema->string()->enum(['idea', 'planned', 'open', 'in_progress', 'review', 'done']))->description('Filter to task statuses.'),
            'include_released' => $schema->boolean()->description('false excludes released milestones. Default true.'),
        ];
    }
}
