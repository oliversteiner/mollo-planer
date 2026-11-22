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

#[Name('import_plan')]
#[Description('Writes a planer-v2 document into a project (or creates one without project_id). Always run dry_run: true first. mode "full" (default) checks revision and trashes missing items; mode "partial" only adds/updates. New IDs: m-new-1, t-new-1, k-new-1.')]
class ImportPlanTool extends Tool
{
    use RunsGraphQL;

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

    public function handle(Request $request): Response
    {
        $validated = $request->validate(
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

        return $this->respond(function () use ($validated, $plan, $includeDocument): Response {
            $data = app(GraphQLRunner::class)->run(self::MUTATION, [
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
                'errors' => $data['errors'],
                'idMap' => (object) array_column($data['idMap'], 'to', 'from'),
            ];

            if ($includeDocument && $data['document'] !== null) {
                $payload['document'] = json_decode($data['document'], true, 512, JSON_THROW_ON_ERROR);
            }

            $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $data['ok'] ? Response::text($encoded) : Response::error($encoded);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Target project ID; omit to create a new project.'),
            'plan' => $schema->object()->description('planer-v2 document.')->required(),
            'mode' => $schema->string()->enum(['full', 'partial'])->description('"full" (default) or "partial".'),
            'dry_run' => $schema->boolean()->description('true: validate and preview without writing.'),
            'notify_assignees' => $schema->boolean()->description('true: send assignment emails. Default false.'),
            'template_id' => $schema->string()->description('Only without project_id: template origin ID.'),
            'idea_id' => $schema->string()->description('Idea ID to adopt after import.'),
            'append_only' => $schema->boolean()->description('true: error on any modification of existing items. Default false.'),
            'include_document' => $schema->boolean()->description('true: include exported document in response. Default false to save tokens.'),
        ];
    }
}
