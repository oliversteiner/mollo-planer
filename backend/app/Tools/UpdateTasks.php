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
        return 'Sets status, commit, assigneeEmail of tasks, atomically, no export needed. Omitted fields stay, null clears. Optional base_revision: abort if the plan changed since.';
    }

    public function schema(): array
    {
        return Schema::input([
            'project_id' => Schema::string('Project ID.'),
            'base_revision' => Schema::integer('Optional: abort if the plan is newer.'),
            'tasks' => Schema::list(Schema::object([
                'id' => Schema::string('k-<id> or number.'),
                'status' => Schema::string(null, ['idea', 'planned', 'open', 'in_progress', 'review', 'done']),
                'commit' => Schema::string('Hash, "none" or null to clear.'),
                'assigneeEmail' => Schema::string('Member email or null.'),
            ], null, ['id']), 'Tasks to update (max 500).'),
            'dry_run' => Schema::boolean('Preview only.'),
        ], ['project_id', 'tasks']);
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
                'base_revision' => ['sometimes', 'nullable', 'integer'],
                'tasks' => ['required', 'array', 'min:1', 'max:500'],
                'tasks.*.id' => ['required'],
                'tasks.*.status' => ['sometimes', 'in:idea,planned,open,in_progress,review,done'],
                'tasks.*.commit' => ['sometimes', 'nullable', 'string'],
                'tasks.*.assigneeEmail' => ['sometimes', 'nullable', 'string'],
                'dry_run' => ['sometimes', 'boolean'],
            ],
            [
                'project_id.required' => 'Give project_id (from list_projects).',
                'tasks.required' => 'Give tasks: [{ id, status?, commit?, assigneeEmail? }].',
                'tasks.*.status.in' => 'status must be idea, planned, open, in_progress, review or done.',
            ],
        );

        try {
            return ToolResult::json(app(PlanTaskUpdates::class)->apply(
                $actor,
                (string) $validated['project_id'],
                $validated['tasks'],
                isset($validated['base_revision']) ? (int) $validated['base_revision'] : null,
                (bool) ($validated['dry_run'] ?? false),
            ));
        } catch (PlanTaskUpdatesRefused $refused) {
            return ToolResult::error($refused->getMessage());
        }
    }
}
