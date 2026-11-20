<?php

namespace App\Tools;

use App\Models\User;

class ImportPlan extends Tool
{
    public function name(): string
    {
        return 'import_plan';
    }

    public function description(): string
    {
        return 'Writes a planer-v2 document into a project (project_id), or into a new project (without project_id). Work on the document that export_plan gave you. mode "full" (default): the document is the whole plan, what it lacks goes to the trash as one undoable deletion, and its revision must be the one of the project. mode "partial": adds and changes, never deletes, fields you leave out stay, the revision is not checked. New items get ids like m-new-1, t-new-1, k-new-1; the result maps them to real ids. Always try dry_run: true first: nothing is written, the result lists every change and every error with its path in the document. All or nothing, with your rights: each change is checked on its own. No mails unless notify_assignees is true.';
    }

    private const MUTATION = <<<'GRAPHQL'
        mutation ($projectId: ID, $document: String!, $mode: ImportMode!, $dryRun: Boolean!, $notify: Boolean!, $templateId: ID, $ideaId: ID, $appendOnly: Boolean!) {
          importPlan(projectId: $projectId, document: $document, mode: $mode, dryRun: $dryRun, notifyAssignees: $notify, templateId: $templateId, ideaId: $ideaId, appendOnly: $appendOnly) {
            ok dryRun projectId document
            changes { kind path id fields title }
            errors { path kind message }
            idMap { from to }
          }
        }
        GRAPHQL;

    public function schema(): array
    {
        return Schema::input([
            'project_id' => Schema::string('Id of the project (from list_projects). Leave out to create a new project from the plan.'),
            'plan' => Schema::object(null, 'The planer-v2 document (format, revision, project, milestones).'),
            'mode' => Schema::string('"full" (default) or "partial".', ['full', 'partial']),
            'dry_run' => Schema::boolean('true: write nothing, only report the changes and errors.'),
            'notify_assignees' => Schema::boolean('true: send the assignment mails. Default false.'),
            'template_id' => Schema::string('Only without project_id: the template the new project comes from (sets its origin; the outline and labels must stand in the plan).'),
            'idea_id' => Schema::string('The idea (yours) this plan is made from: it is adopted afterwards.'),
            'append_only' => Schema::boolean('true: only add; any change of something that exists is an error. Default false.'),
        ], ['plan']);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate(
            $arguments,
            [
                'project_id' => ['sometimes', 'nullable'],
                'plan' => ['required'],
                'mode' => ['sometimes', 'in:full,partial'],
                'dry_run' => ['sometimes', 'boolean'],
                'notify_assignees' => ['sometimes', 'boolean'],
                'template_id' => ['sometimes', 'nullable'],
                'idea_id' => ['sometimes', 'nullable'],
                'append_only' => ['sometimes', 'boolean'],
            ],
            [
                'plan.required' => 'Give the plan: a planer-v2 document, for example the one export_plan returned.',
                'mode.in' => 'mode is "full" (the document is the whole plan) or "partial" (add and change only).',
            ],
        );
        $plan = $validated['plan'];

        $data = $this->graphql($actor, self::MUTATION, [
            'projectId' => isset($validated['project_id']) ? (string) $validated['project_id'] : null,
            'document' => is_string($plan) ? $plan : json_encode($plan, JSON_THROW_ON_ERROR),
            'mode' => strtoupper($validated['mode'] ?? 'full'),
            'dryRun' => (bool) ($validated['dry_run'] ?? false),
            'notify' => (bool) ($validated['notify_assignees'] ?? false),
            'templateId' => isset($validated['template_id']) ? (string) $validated['template_id'] : null,
            'ideaId' => isset($validated['idea_id']) ? (string) $validated['idea_id'] : null,
            'appendOnly' => (bool) ($validated['append_only'] ?? false),
        ])['importPlan'];

        $data['idMap'] = (object) array_column($data['idMap'], 'to', 'from');
        $data['document'] = $data['document'] === null ? null : json_decode($data['document'], true, 512, JSON_THROW_ON_ERROR);

        return $data['ok'] ? ToolResult::json($data) : ToolResult::error(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
