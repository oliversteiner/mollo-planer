<?php

namespace App\Tools;

use App\Models\User;

class ExportPlan extends Tool
{
    public function name(): string
    {
        return 'export_plan';
    }

    public function description(): string
    {
        return 'Exports the whole plan of a project as a planer-v2 document: project, members, labels, milestones, their todos and the tasks of each todo, with the revision. Use it before any change, so you work on the current state. The ids (m-<id>, t-<id>, k-<id>) are the ones of the app. With a filter (version, milestone_ids, task_status, include_released) you get an excerpt: a valid document marked excerpt: true. Write it back only with import_plan mode partial; mode full refuses it.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return Schema::input([
            'project_id' => Schema::string('Id of the project (from list_projects).'),
            'version' => Schema::string('Only the milestone of this release version, for example "1.21".'),
            'milestone_ids' => Schema::list(Schema::string(), 'Only these milestones (m-<id> or the number).'),
            'task_status' => Schema::list(Schema::string(null, ['idea', 'planned', 'open', 'in_progress', 'review', 'done']), 'Only tasks with these statuses; todos and milestones without such a task are left out.'),
            'include_released' => Schema::boolean('false: leave out released milestones. Default true.'),
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
            ],
            ['project_id.required' => 'Give the project_id, for example from list_projects.'],
        );

        $data = $this->graphql(
            $actor,
            'query ($id: ID!, $version: String, $ids: [ID!], $status: [TaskStatus!], $released: Boolean!) { exportPlan(projectId: $id, version: $version, milestoneIds: $ids, taskStatus: $status, includeReleased: $released) }',
            [
                'id' => (string) $validated['project_id'],
                'version' => isset($validated['version']) ? (string) $validated['version'] : null,
                'ids' => array_map('strval', $validated['milestone_ids'] ?? []) ?: null,
                'status' => array_map('strtoupper', $validated['task_status'] ?? []) ?: null,
                'released' => (bool) ($validated['include_released'] ?? true),
            ],
        );

        return ToolResult::json(['document' => json_decode((string) $data['exportPlan'], true, 512, JSON_THROW_ON_ERROR)]);
    }
}
