<?php

namespace App\Services;

use App\Models\User;
use App\Tools\Support\GraphQLRunner;

/**
 * Sets status, commit, assignee, title and labels of several tasks at once, as a person (task 34): the most common work of an agent,
 * and the same work the assistant does later. It adds no rules of its own. The change goes through the export and
 * the import (mode partial) of the plan, so the rights, the commit duty, the release rule and the membership of an
 * assignee are those of the app, and all of it is one transaction.
 */
class PlanTaskUpdates
{
    private const FIELDS = ['status', 'commit', 'assigneeEmail', 'title', 'labels'];

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

    /**
     * With a base revision the call is refused when the plan has changed since (the refusal carries the current state of
     * the tasks asked for, so no new export is needed). Without one, every task and field is changed on its own: a change
     * by somebody else in the meantime is no reason to refuse; only if the plan changes between reading and writing the
     * call tries again (up to three times).
     *
     * @param  list<array<string, mixed>>  $updates  { id, status?, commit?, assigneeEmail? } per task
     * @return array{ok: true, dry_run?: true, revision: int, changed: list<array<string, mixed>>, unchanged?: int}
     *
     * @throws PlanTaskUpdatesRefused with the sentence or the errors (JSON) the AI client reads
     */
    public function apply(User $actor, string $projectId, array $updates, ?int $baseRevision, bool $dryRun = false): array
    {
        $attempts = $baseRevision === null ? 3 : 1;

        for ($try = 1; ; $try++) {
            try {
                return $this->attempt($actor, $projectId, $updates, $baseRevision, $dryRun);
            } catch (PlanTaskUpdatesRefused $refused) {
                if ($refused->revisionConflict && $try < $attempts) {
                    continue;
                }

                throw $refused;
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $updates
     * @return array{ok: true, dry_run?: true, revision: int, changed: list<array<string, mixed>>, unchanged?: int}
     */
    private function attempt(User $actor, string $projectId, array $updates, ?int $baseRevision, bool $dryRun): array
    {
        $runner = app(GraphQLRunner::class);
        $document = json_decode((string) $runner->run(self::EXPORT, ['id' => $projectId], $actor)['exportPlan'], true, 512, JSON_THROW_ON_ERROR);

        if ($baseRevision !== null && $baseRevision !== (int) $document['revision']) {
            throw $this->conflict($document, $updates, $baseRevision);
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

        foreach ($updates as $index => $update) {
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
            throw $this->failure($errors);
        }

        $result = $runner->run(self::IMPORT, [
            'projectId' => $projectId,
            'document' => json_encode($this->excerpt($document, $changes), JSON_THROW_ON_ERROR),
            'dryRun' => $dryRun,
            'expectedRevision' => $baseRevision ?? (int) $document['revision'],
        ], $actor)['importPlan'];

        if (! $result['ok']) {
            throw $this->failure($result['errors'], collect($result['errors'])->contains(fn (array $error): bool => $error['kind'] === 'revision'));
        }

        $revision = $dryRun
            ? (int) $document['revision']
            : (int) ($runner->run(self::REVISION, ['id' => $projectId], $actor)['project']['planerRevision'] ?? $document['revision']);

        // Short: per changed task its id and only the fields that changed, as [old, new]; the others only as a number.
        $tasks = [];
        foreach ($after as $id => $values) {
            $fields = [];

            foreach ($values as $field => $new) {
                if ($new !== $before[$id][$field]) {
                    $fields[$field] = [$before[$id][$field], $new];
                }
            }

            if ($fields !== []) {
                $tasks[] = ['id' => $id, ...$fields];
            }
        }

        return array_filter([
            'ok' => true,
            'dry_run' => $dryRun ? true : null,
            'revision' => $revision,
            'changed' => $tasks,
            'unchanged' => count($after) - count($tasks) ?: null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * The refusal for an old base revision: the current revision and the current state of the tasks asked for only.
     *
     * @param  array<string, mixed>  $document
     * @param  list<array<string, mixed>>  $updates
     */
    private function conflict(array $document, array $updates, int $baseRevision): PlanTaskUpdatesRefused
    {
        $wanted = array_map(fn (array $update): string => $this->taskId($update['id']), $updates);
        $current = [];

        foreach ($document['milestones'] as $milestone) {
            foreach ($milestone['todos'] ?? [] as $todo) {
                foreach ($todo['tasks'] ?? [] as $task) {
                    if (in_array($task['id'], $wanted, true)) {
                        $current[] = array_filter(['id' => $task['id'], 'title' => $task['title'], ...$this->fieldsOf($task)], fn (mixed $value): bool => $value !== null);
                    }
                }
            }
        }

        return new PlanTaskUpdatesRefused(json_encode([
            'ok' => false,
            'error' => 'revision',
            'message' => __('planer.update_tasks.revision', ['file' => $baseRevision, 'project' => $document['revision']]),
            'revision' => (int) $document['revision'],
            'tasks' => $current,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
     * @return array{status: mixed, commit: mixed, assigneeEmail: mixed, title: mixed, labels: list<string>}
     */
    private function fieldsOf(array $task): array
    {
        return ['status' => $task['status'] ?? null, 'commit' => $task['commit'] ?? null, 'assigneeEmail' => $task['assigneeEmail'] ?? null, 'title' => $task['title'] ?? null, 'labels' => array_values($task['labels'] ?? [])];
    }

    private function taskId(mixed $id): string
    {
        $id = (string) $id;

        return str_starts_with($id, 'k-') ? $id : "k-{$id}";
    }

    /**
     * @param  list<array<string, mixed>>  $errors
     */
    private function failure(array $errors, bool $revisionConflict = false): PlanTaskUpdatesRefused
    {
        return new PlanTaskUpdatesRefused(json_encode(['ok' => false, 'errors' => $errors], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $revisionConflict);
    }
}
