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

/**
 * Sets status, commit and assignee of several tasks at once: the most common work of an agent. It adds no rules of its
 * own. The change goes through export_plan and import_plan (mode partial) of the app, so the rights, the commit duty,
 * the release rule and the membership of an assignee are the ones of the app, and all of it is one transaction.
 */
#[Name('update_tasks')]
#[Description('Sets status, commit and/or assigneeEmail of tasks in one atomic step. tasks: [{ id (k-<id>), status?, commit?, assigneeEmail? }]; omitted fields stay, null clears. Use base_revision (from export_plan) against concurrent edits and dry_run: true first.')]
class UpdateTasksTool extends Tool
{
    use RunsGraphQL;

    private const FIELDS = ['status', 'commit', 'assigneeEmail'];

    private const EXPORT = 'query ($id: ID!) { exportPlan(projectId: $id) }';

    private const REVISION = 'query ($id: ID!) { project(id: $id) { planerRevision } }';

    private const IMPORT = <<<'GRAPHQL'
        mutation ($projectId: ID, $document: String!, $dryRun: Boolean!, $expectedRevision: Int!) {
          importPlan(projectId: $projectId, document: $document, mode: PARTIAL, dryRun: $dryRun, notifyAssignees: false, appendOnly: false, expectedRevision: $expectedRevision) {
            ok dryRun
            errors { path kind message }
          }
        }
        GRAPHQL;

    public function handle(Request $request): Response
    {
        $validated = $request->validate(
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

        $dryRun = (bool) ($validated['dry_run'] ?? false);

        return $this->respond(function () use ($validated, $dryRun): Response {
            $runner = app(GraphQLRunner::class);
            $projectId = (string) $validated['project_id'];
            $document = json_decode((string) $runner->run(self::EXPORT, ['id' => $projectId])['exportPlan'], true, 512, JSON_THROW_ON_ERROR);

            $base = (int) $validated['base_revision'];

            if ($base !== (int) $document['revision']) {
                return Response::error(__('planer.update_tasks.revision', ['file' => $base, 'project' => $document['revision']]));
            }

            $known = [];
            foreach ($document['milestones'] as $mi => $milestone) {
                foreach ($milestone['todos'] ?? [] as $ti => $todo) {
                    foreach ($todo['tasks'] ?? [] as $ki => $task) {
                        $known[$task['id']] = [$mi, $ti, $ki];
                    }
                }
            }

            $errors = [];
            $changes = [];
            $before = [];
            $after = [];

            foreach ($validated['tasks'] as $index => $update) {
                $id = $this->taskId($update['id']);

                if (! isset($known[$id])) {
                    $errors[] = ['path' => "tasks[{$index}].id", 'kind' => 'unknown_id', 'message' => __('planer.import.unknown_id', ['id' => $id])];

                    continue;
                }

                [$mi, $ti, $ki] = $known[$id];
                $current = $document['milestones'][$mi]['todos'][$ti]['tasks'][$ki];
                $before[$id] = $this->fieldsOf($current);

                foreach (self::FIELDS as $field) {
                    if (array_key_exists($field, $update)) {
                        $current[$field] = $update[$field];
                    }
                }

                $changes[$mi][$ti][$ki] = $current;
                $after[$id] = $this->fieldsOf($current);
            }

            if ($errors !== []) {
                return $this->failure($errors);
            }

            $result = $runner->run(self::IMPORT, [
                'projectId' => $projectId,
                'document' => json_encode($this->excerpt($document, $changes), JSON_THROW_ON_ERROR),
                'dryRun' => $dryRun,
                'expectedRevision' => $base,
            ])['importPlan'];

            if (! $result['ok']) {
                return $this->failure($result['errors']);
            }

            $revision = $dryRun
                ? (int) $document['revision']
                : (int) ($runner->run(self::REVISION, ['id' => $projectId])['project']['planerRevision'] ?? $document['revision']);

            $tasks = [];
            foreach ($after as $id => $values) {
                $diffBefore = array_diff_assoc($before[$id], $values);
                $diffAfter = array_diff_assoc($values, $before[$id]);

                if ($diffAfter !== []) {
                    $tasks[] = ['id' => $id, 'before' => $diffBefore, 'after' => $diffAfter];
                }
            }

            return Response::text(json_encode(
                ['ok' => true, 'dry_run' => $dryRun, 'revision' => $revision, 'changed' => $tasks],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        });
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<int, array<int, array<int, array<string, mixed>>>>  $changes  Changed tasks by milestone, todo and task place
     * @return array<string, mixed>
     */
    private function excerpt(array $document, array $changes): array
    {
        $milestones = [];

        foreach ($changes as $mi => $todos) {
            $milestone = $document['milestones'][$mi];
            $milestone['todos'] = [];

            foreach ($todos as $ti => $tasks) {
                $todo = $document['milestones'][$mi]['todos'][$ti];
                $todo['tasks'] = array_values($tasks);
                $milestone['todos'][] = $todo;
            }

            $milestones[] = $milestone;
        }

        return [...$document, 'excerpt' => true, 'milestones' => $milestones];
    }

    /**
     * @param  array<string, mixed>  $task
     * @return array{status: mixed, commit: mixed, assigneeEmail: mixed}
     */
    private function fieldsOf(array $task): array
    {
        return ['status' => $task['status'] ?? null, 'commit' => $task['commit'] ?? null, 'assigneeEmail' => $task['assigneeEmail'] ?? null];
    }

    private function taskId(mixed $id): string
    {
        $id = (string) $id;

        return str_starts_with($id, 'k-') ? $id : "k-{$id}";
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     */
    private function failure(array $errors): Response
    {
        return Response::error(json_encode(['ok' => false, 'errors' => $errors], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ID (from list_projects).')->required(),
            'base_revision' => $schema->integer()->description('Required revision from export_plan; aborts if project changed meanwhile.')->required(),
            'tasks' => $schema->array()->items($schema->object([
                'id' => $schema->string()->description('Task ID (k-<id> or number).')->required(),
                'status' => $schema->string()->enum(['idea', 'planned', 'open', 'in_progress', 'review', 'done']),
                'commit' => $schema->string()->description('Commit hash, "none", or null to clear.'),
                'assigneeEmail' => $schema->string()->description('Member email, or null to unassign.'),
            ]))->description('Tasks to update (max 500).')->required(),
            'dry_run' => $schema->boolean()->description('true: preview changes without writing.'),
        ];
    }
}
