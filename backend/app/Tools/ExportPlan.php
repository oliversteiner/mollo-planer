<?php

namespace App\Tools;

use App\Models\User;
use App\Planer\PlanView;

class ExportPlan extends Tool
{
    public function name(): string
    {
        return 'export_plan';
    }

    public function description(): string
    {
        return 'Exports a project as planer-v2 JSON (revision, m-/t-/k- IDs). On large plans use view "outline" or filters; outline and filtered results are excerpts.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return Schema::input([
            'project_id' => Schema::string('Project ID (list_projects).'),
            'version' => Schema::string('Release version, e.g. "1.21".'),
            'milestone_ids' => Schema::list(Schema::string(), 'm-<id> or number.'),
            'task_status' => Schema::list(Schema::string(null, ['idea', 'planned', 'open', 'in_progress', 'review', 'done']), 'Filter to task statuses.'),
            'include_released' => Schema::boolean('false: skip released milestones.'),
            'view' => Schema::string('"outline": milestones with task counts. Default "full".', ['full', 'outline']),
            'include_descriptions' => Schema::boolean('false: no descriptions (excerpt).'),
        ], ['project_id']);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate(
            $arguments,
            [
                'project_id' => ['required'],
                'version' => ['sometimes', 'nullable', 'string'],
                'milestone_ids' => ['sometimes', 'array'],
                'task_status' => ['sometimes', 'array'],
                'task_status.*' => ['in:idea,planned,open,in_progress,review,done'],
                'include_released' => ['sometimes', 'boolean'],
                'view' => ['sometimes', 'in:full,outline'],
                'include_descriptions' => ['sometimes', 'boolean'],
            ],
            ['project_id.required' => 'Give project_id (from list_projects).'],
        );

        $data = $this->graphql(
            $actor,
            'query ($id: ID!, $version: String, $ids: [ID!], $status: [TaskStatus!], $released: Boolean!) { exportPlan(projectId: $id, version: $version, milestoneIds: $ids, taskStatus: $status, includeReleased: $released) }',
            [
                'id' => (string) $validated['project_id'],
                'version' => isset($validated['version']) ? (string) $validated['version'] : null,
                'ids' => array_map(fn ($id): string => preg_replace('/^m-/', '', (string) $id), $validated['milestone_ids'] ?? []) ?: null,
                'status' => array_map('strtoupper', $validated['task_status'] ?? []) ?: null,
                'released' => (bool) ($validated['include_released'] ?? true),
            ],
        );

        $document = json_decode((string) $data['exportPlan'], true, 512, JSON_THROW_ON_ERROR);

        if (($validated['view'] ?? 'full') === 'outline') {
            $document = PlanView::outline($document);
        } elseif (! ($validated['include_descriptions'] ?? true)) {
            $document = PlanView::withoutDescriptions($document);
        }

        return ToolResult::json(['document' => $document]);
    }
}
