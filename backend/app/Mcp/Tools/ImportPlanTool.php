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
#[Description('Writes a planer-v2 document into a project (project_id), or into a new project (without project_id). Work on the document that export_plan gave you. mode "full" (default): the document is the whole plan, what it lacks goes to the trash as one undoable deletion, and its revision must be the one of the project. mode "partial": adds and changes, never deletes, fields you leave out stay, the revision is not checked. New items get ids like m-new-1, t-new-1, k-new-1; the result maps them to real ids. Always try dry_run: true first: nothing is written, the result lists every change and every error with its path in the document. All or nothing, with your rights: each change is checked on its own. No mails unless notify_assignees is true.')]
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
            ],
            [
                'plan.required' => 'Give the plan: a planer-v2 document, for example the one export_plan returned.',
                'mode.in' => 'mode is "full" (the document is the whole plan) or "partial" (add and change only).',
            ],
        );

        $plan = $validated['plan'];

        return $this->respond(function () use ($validated, $plan): Response {
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

            $data['idMap'] = (object) array_column($data['idMap'], 'to', 'from');
            $data['document'] = $data['document'] === null ? null : json_decode($data['document'], true, 512, JSON_THROW_ON_ERROR);

            return $data['ok'] ? Response::json($data) : Response::error(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Id of the project (from list_projects). Leave out to create a new project from the plan.'),
            'plan' => $schema->object()->description('The planer-v2 document (format, revision, project, milestones).')->required(),
            'mode' => $schema->string()->enum(['full', 'partial'])->description('"full" (default) or "partial".'),
            'dry_run' => $schema->boolean()->description('true: write nothing, only report the changes and errors.'),
            'notify_assignees' => $schema->boolean()->description('true: send the assignment mails. Default false.'),
            'template_id' => $schema->string()->description('Only without project_id: the template the new project comes from (sets its origin; the outline and labels must stand in the plan).'),
            'idea_id' => $schema->string()->description('The idea (yours) this plan is made from: it is adopted afterwards.'),
            'append_only' => $schema->boolean()->description('true: only add; any change of something that exists is an error. Default false.'),
        ];
    }
}
