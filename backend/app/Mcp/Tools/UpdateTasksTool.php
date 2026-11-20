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
#[Description('Sets status, commit and/or assigneeEmail of several tasks in one step (the usual work of an agent). tasks: a list of { id (k-<id>), status?, commit?, assigneeEmail? }; what you leave out stays, null clears commit or assignee. All or nothing, with your rights and the rules of the app (commit when done in a released version, assignee must be a member). base_revision (from export_plan) protects against changes made meanwhile: if the plan moved on, nothing is written. dry_run: true shows the result without writing. Needs the rights to export and import the plan. For anything else (titles, new tasks, structure) use import_plan.')]
class UpdateTasksTool extends Tool
{
    use RunsGraphQL;

    private const FIELDS = ['status', 'commit', 'assigneeEmail'];

    private const EXPORT = 'query ($id: ID!) { exportPlan(projectId: $id) }';

    private const IMPORT = <<<'GRAPHQL'
        mutation ($projectId: ID, $document: String!, $dryRun: Boolean!) {
          importPlan(projectId: $projectId, document: $document, mode: PARTIAL, dryRun: $dryRun, notifyAssignees: false, appendOnly: false) {
            ok dryRun document
            changes { kind path id fields }
            errors { path kind message }
          }
        }
        GRAPHQL;

    public function handle(Request $request): Response
    {
        $validated = $request->validate(
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

        $dryRun = (bool) ($validated['dry_run'] ?? false);

        return $this->respond(function () use ($validated, $dryRun): Response {
            $runner = app(GraphQLRunner::class);
            $projectId = (string) $validated['project_id'];
            $document = json_decode((string) $runner->run(self::EXPORT, ['id' => $projectId])['exportPlan'], true, 512, JSON_THROW_ON_ERROR);

            $base = $validated['base_revision'] ?? null;

            if ($base !== null && (int) $base !== (int) $document['revision']) {
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

                // The file has no null: a cleared commit or assignee is a missing field.
                $current = array_filter($current, fn ($value): bool => $value !== null);
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
            ])['importPlan'];

            if (! $result['ok']) {
                return $this->failure($result['errors']);
            }

            $revision = $result['document'] === null
                ? (int) $document['revision']
                : (int) json_decode($result['document'], true, 512, JSON_THROW_ON_ERROR)['revision'];

            $tasks = [];
            foreach ($after as $id => $values) {
                if ($values !== $before[$id]) {
                    $tasks[] = ['id' => $id, 'before' => $before[$id], 'after' => $values];
                }
            }

            return Response::json(['ok' => true, 'dry_run' => $dryRun, 'revision' => $revision, 'changed' => $tasks]);
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
            'project_id' => $schema->string()->description('Id of the project (from list_projects).')->required(),
            'base_revision' => $schema->integer()->description('The revision you worked on (from export_plan). If the plan has another revision now, nothing is written.'),
            'tasks' => $schema->array()->items($schema->object([
                'id' => $schema->string()->description('Task id, k-<id> or the number.')->required(),
                'status' => $schema->string()->enum(['idea', 'planned', 'open', 'in_progress', 'review', 'done']),
                'commit' => $schema->string()->description('Commit hash, "none", or null to clear.'),
                'assigneeEmail' => $schema->string()->description('E-mail of a member of the project, or null for nobody.'),
            ]))->description('The tasks to change, up to 500.')->required(),
            'dry_run' => $schema->boolean()->description('true: write nothing, show what would change.'),
        ];
    }
}
