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
        return 'Writes a planer-v2 document into a project (a new project without project_id). Dry-run first (dry_run: true). mode "full" (default) trashes what is missing, "partial" only adds and updates. Unknown assignees are skipped with a warning.';
    }

    private const MUTATION = <<<'GRAPHQL'
        mutation ($projectId: ID, $document: String!, $mode: ImportMode!, $dryRun: Boolean!, $notify: Boolean!, $templateId: ID, $ideaId: ID, $appendOnly: Boolean!, $expectedRevision: Int) {
          importPlan(projectId: $projectId, document: $document, mode: $mode, dryRun: $dryRun, notifyAssignees: $notify, templateId: $templateId, ideaId: $ideaId, appendOnly: $appendOnly, expectedRevision: $expectedRevision) {
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
            'project_id' => Schema::string('Target project; omit to create one.'),
            'plan' => Schema::object(null, 'planer-v2 document. Fields per level and limits: see the instructions of the server, which also link the JSON schema. Unknown fields are refused.'),
            'mode' => Schema::string('"full" (default) or "partial".', ['full', 'partial']),
            'dry_run' => Schema::boolean('Validate only, write nothing.'),
            'notify_assignees' => Schema::boolean('Send assignment mails. Default false.'),
            'template_id' => Schema::string('New project only: template ID.'),
            'idea_id' => Schema::string('Idea to adopt.'),
            'append_only' => Schema::boolean('Refuse changes to existing items.'),
            'base_revision' => Schema::integer('Optional: abort if the plan is newer.'),
            'include_document' => Schema::boolean('Return the document too. Default false.'),
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
                'base_revision' => ['sometimes', 'nullable', 'integer'],
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
            'expectedRevision' => isset($validated['base_revision']) ? (int) $validated['base_revision'] : null,
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
