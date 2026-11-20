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
        return 'Sets status, commit and/or assigneeEmail of several tasks in one step (the usual work of an agent). tasks: a list of { id (k-<id>), status?, commit?, assigneeEmail? }; what you leave out stays, null clears commit or assignee. All or nothing, with your rights and the rules of the app (commit when done in a released version, assignee must be a member). base_revision (from export_plan) protects against changes made meanwhile: if the plan moved on, nothing is written. dry_run: true shows the result without writing. Needs the rights to export and import the plan. For anything else (titles, new tasks, structure) use import_plan.';
    }

    public function schema(): array
    {
        return Schema::input([
            'project_id' => Schema::string('Id of the project (from list_projects).'),
            'base_revision' => Schema::integer('The revision you worked on (from export_plan). If the plan has another revision now, nothing is written.'),
            'tasks' => Schema::list(Schema::object([
                'id' => Schema::string('Task id, k-<id> or the number.'),
                'status' => Schema::string(null, ['idea', 'planned', 'open', 'in_progress', 'review', 'done']),
                'commit' => Schema::string('Commit hash, "none", or null to clear.'),
                'assigneeEmail' => Schema::string('E-mail of a member of the project, or null for nobody.'),
            ], null, ['id']), 'The tasks to change, up to 500.'),
            'dry_run' => Schema::boolean('true: write nothing, show what would change.'),
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
                'project_id.required' => 'Give the project_id, for example from list_projects.',
                'tasks.required' => 'Give tasks: a list of { id, status?, commit?, assigneeEmail? }.',
                'tasks.*.status.in' => 'status is one of idea, planned, open, in_progress, review, done.',
            ],
        );

        try {
            return ToolResult::json(app(PlanTaskUpdates::class)->apply(
                $actor,
                (string) $validated['project_id'],
                $validated['tasks'],
                (bool) ($validated['dry_run'] ?? false),
                isset($validated['base_revision']) ? (int) $validated['base_revision'] : null,
            ));
        } catch (PlanTaskUpdatesRefused $refused) {
            return ToolResult::error($refused->getMessage());
        }
    }
}
