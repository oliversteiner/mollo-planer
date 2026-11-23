<?php

namespace App\Tools;

use App\Models\User;

class ImportPlan extends Tool
{
    /** How many errors and how many warnings the answer lists. */
    private const LISTED = 20;

    public function name(): string
    {
        return 'import_plan';
    }

    public function description(): string
    {
        return 'Writes a planer-v2 document into a project (or creates one without project_id). Always run dry_run: true first. mode "full" (default) checks revision and trashes missing items; mode "partial" only adds/updates. Tasks whose assignee is unknown or not a project member are imported without that assignee and listed in warnings (path, email, message). New IDs: m-new-1, t-new-1, k-new-1.';
    }

    private const MUTATION = <<<'GRAPHQL'
        mutation ($projectId: ID, $document: String!, $mode: ImportMode!, $dryRun: Boolean!, $notify: Boolean!, $templateId: ID, $ideaId: ID, $appendOnly: Boolean!) {
          importPlan(projectId: $projectId, document: $document, mode: $mode, dryRun: $dryRun, notifyAssignees: $notify, templateId: $templateId, ideaId: $ideaId, appendOnly: $appendOnly) {
            ok dryRun projectId document
            changes { kind path id fields title }
            errors { path kind message }
            warnings { path email message }
            idMap { from to }
          }
        }
        GRAPHQL;

    public function schema(): array
    {
        return Schema::input([
            'project_id' => Schema::string('Target project ID; omit to create a new project.'),
            'plan' => Schema::object(null, 'planer-v2 document.'),
            'mode' => Schema::string('"full" (default) or "partial".', ['full', 'partial']),
            'dry_run' => Schema::boolean('true: validate and preview without writing.'),
            'notify_assignees' => Schema::boolean('true: send assignment emails. Default false.'),
            'template_id' => Schema::string('Only without project_id: template origin ID.'),
            'idea_id' => Schema::string('Idea ID to adopt after import.'),
            'append_only' => Schema::boolean('true: error on any modification of existing items. Default false.'),
            'include_document' => Schema::boolean('true: include exported document in response. Default false to save tokens.'),
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
                'include_document' => ['sometimes', 'boolean'],
            ],
            [
                'plan.required' => 'Give plan (planer-v2 document).',
                'mode.in' => 'mode must be "full" or "partial".',
            ],
        );
        $plan = $validated['plan'];
        $includeDocument = (bool) ($validated['include_document'] ?? false);

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

        $payload = [
            'ok' => $data['ok'],
            'dryRun' => $data['dryRun'],
            'projectId' => $data['projectId'],
            'changes' => $data['changes'],
            'errors' => array_slice($data['errors'], 0, self::LISTED),
            'warnings' => array_slice($data['warnings'], 0, self::LISTED),
            'idMap' => (object) array_column($data['idMap'], 'to', 'from'),
        ];

        // Errors and warnings: the first ones, and how many there are in all (a long list costs tokens and says it all).
        foreach (['errors', 'warnings'] as $kind) {
            if (count($data[$kind]) > self::LISTED) {
                $payload["{$kind}_total"] = count($data[$kind]);
            }
        }

        if ($includeDocument && $data['document'] !== null) {
            $payload['document'] = json_decode($data['document'], true, 512, JSON_THROW_ON_ERROR);
        }

        return $data['ok'] ? ToolResult::json($payload) : ToolResult::error(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
