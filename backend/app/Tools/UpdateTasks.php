<?php

namespace App\Tools;

use App\Models\User;
use App\Services\PlanTaskUpdates;
use App\Services\PlanTaskUpdatesRefused;

class UpdateTasks extends Tool
{
    public function name(): string
    {
        return 'update_tasks';
    }

    public function description(): string
    {
        return 'Sets status, commit and/or assigneeEmail of tasks in one atomic step. tasks: [{ id (k-<id>), status?, commit?, assigneeEmail? }]; omitted fields stay, null clears. Use base_revision (from export_plan) against concurrent edits and dry_run: true first.';
    }

    public function schema(): array
    {
        return Schema::input([
            'project_id' => Schema::string('Project ID (from list_projects).'),
            'base_revision' => Schema::integer('Required revision from export_plan; aborts if project changed meanwhile.'),
            'tasks' => Schema::list(Schema::object([
                'id' => Schema::string('Task ID (k-<id> or number).'),
                'status' => Schema::string(null, ['idea', 'planned', 'open', 'in_progress', 'review', 'done']),
                'commit' => Schema::string('Commit hash, "none", or null to clear.'),
                'assigneeEmail' => Schema::string('Member email, or null to unassign.'),
            ], null, ['id']), 'Tasks to update (max 500).'),
            'dry_run' => Schema::boolean('true: preview changes without writing.'),
        ], ['project_id', 'base_revision', 'tasks']);
    }

    /**
     * The work is in PlanTaskUpdates (the assistant uses it too); this only checks the arguments and names the person.
     */
    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate(
            $arguments,
            [
                'project_id' => ['required'],
                'base_revision' => ['required', 'integer'],
                'tasks' => ['required', 'array', 'min:1', 'max:500'],
                'tasks.*.id' => ['required'],
                'tasks.*.status' => ['sometimes', 'in:idea,planned,open,in_progress,review,done'],
                'tasks.*.commit' => ['sometimes', 'nullable', 'string'],
                'tasks.*.assigneeEmail' => ['sometimes', 'nullable', 'string'],
                'dry_run' => ['sometimes', 'boolean'],
            ],
            [
                'project_id.required' => 'Give project_id (from list_projects).',
                'base_revision.required' => 'Give base_revision from export_plan.',
                'tasks.required' => 'Give tasks: [{ id, status?, commit?, assigneeEmail? }].',
                'tasks.*.status.in' => 'status must be idea, planned, open, in_progress, review or done.',
            ],
        );

        try {
            return ToolResult::json(app(PlanTaskUpdates::class)->apply(
                $actor,
                (string) $validated['project_id'],
                $validated['tasks'],
                (int) $validated['base_revision'],
                (bool) ($validated['dry_run'] ?? false),
            ));
        } catch (PlanTaskUpdatesRefused $refused) {
            return ToolResult::error($refused->getMessage());
        }
    }
}
