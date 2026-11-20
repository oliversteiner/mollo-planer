<?php

namespace App\Services;

use App\Models\User;
use App\Tools\Support\GraphQLRunner;

/**
 * Sets status, commit and assignee of several tasks at once, as a person (task 34): the most common work of an agent,
 * and the same work the assistant does later. It adds no rules of its own. The change goes through the export and
 * the import (mode partial) of the plan, so the rights, the commit duty, the release rule and the membership of an
 * assignee are those of the app, and all of it is one transaction.
 */
class PlanTaskUpdates
{
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

    /**
     * @param  list<array<string, mixed>>  $updates  { id, status?, commit?, assigneeEmail? } per task
     * @return array{ok: true, dry_run: bool, revision: int, changed: list<array<string, mixed>>}
     *
     * @throws PlanTaskUpdatesRefused with the sentence or the errors (JSON) the AI client reads
     */
    public function apply(User $actor, string $projectId, array $updates, bool $dryRun = false, ?int $baseRevision = null): array
    {
        $runner = app(GraphQLRunner::class);
        $document = json_decode((string) $runner->run(self::EXPORT, ['id' => $projectId], $actor)['exportPlan'], true, 512, JSON_THROW_ON_ERROR);

        if ($baseRevision !== null && $baseRevision !== (int) $document['revision']) {
            throw new PlanTaskUpdatesRefused(__('planer.update_tasks.revision', ['file' => $baseRevision, 'project' => $document['revision']]));
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

            // The file has no null: a cleared commit or assignee is a missing field.
            $current = array_filter($current, fn ($value): bool => $value !== null);
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
        ], $actor)['importPlan'];

        if (! $result['ok']) {
            throw $this->failure($result['errors']);
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

        return ['ok' => true, 'dry_run' => $dryRun, 'revision' => $revision, 'changed' => $tasks];
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
    private function failure(array $errors): PlanTaskUpdatesRefused
    {
        return new PlanTaskUpdatesRefused(json_encode(['ok' => false, 'errors' => $errors], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
