<?php

namespace App\Tools;

use App\Models\User;
use App\Tools\Support\ToolArguments;
use Illuminate\Validation\ValidationException;

/**
 * Puts siblings in a new order without an import (task 47): the milestones of a project, the todos of a milestone or the
 * tasks of a todo, or the milestones by their version. The conditions and rights are those of the interface
 * (MilestoneVersionOrder, SiblingOrder); the answer is short: the new revision and the number of moves.
 */
class Reorder extends Tool
{
    private const SIBLINGS = <<<'GRAPHQL'
        mutation ($projectId: ID!, $level: OrderLevel!, $parentId: ID, $ids: [ID!]!, $expectedRevision: Int, $dryRun: Boolean!) {
          reorderSiblings(projectId: $projectId, level: $level, parentId: $parentId, ids: $ids, expectedRevision: $expectedRevision, dryRun: $dryRun) {
            revision moveCount
          }
        }
        GRAPHQL;

    private const BY_VERSION = <<<'GRAPHQL'
        mutation ($projectId: ID!, $expectedRevision: Int, $dryRun: Boolean!) {
          sortMilestones(projectId: $projectId, expectedRevision: $expectedRevision, dryRun: $dryRun) {
            revision moveCount
          }
        }
        GRAPHQL;

    private const PLAN = <<<'GRAPHQL'
        query ($projectId: ID!) {
          milestoneVersionOrder(projectId: $projectId) { order { id title version from to moved } }
        }
        GRAPHQL;

    public function name(): string
    {
        return 'reorder';
    }

    public function description(): string
    {
        return 'Reorders siblings: all ids under parent_id (none: milestones; m-…: its todos; t-…: its tasks), or by "version". Never changes parent (import_plan does).';
    }

    public function schema(): array
    {
        return Schema::input([
            'project_id' => Schema::string('Project ID.'),
            'parent_id' => Schema::string('m-<id>: todos of it. t-<id>: tasks of it. Omit: milestones.'),
            'ids' => Schema::list(Schema::string(), 'All siblings, new order.'),
            'by' => Schema::string('Milestones by version number.', ['version']),
            'base_revision' => Schema::integer('Optional: abort if the plan is newer.'),
            'dry_run' => Schema::boolean('Preview only.'),
        ], ['project_id']);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate(
            $arguments,
            [
                'project_id' => ['required'],
                'parent_id' => ['sometimes', 'nullable', 'string'],
                'ids' => ['sometimes', 'array', 'min:1', 'max:500'],
                'by' => ['sometimes', 'nullable', 'in:version'],
                'base_revision' => ['sometimes', 'nullable', 'integer'],
                'dry_run' => ['sometimes', 'boolean'],
            ],
            [
                'project_id.required' => 'Give project_id (from list_projects).',
                'by.in' => 'by can only be "version".',
            ],
        );

        $projectId = (string) $validated['project_id'];
        $dryRun = (bool) ($validated['dry_run'] ?? false);
        $revision = isset($validated['base_revision']) ? (int) $validated['base_revision'] : null;

        if (($validated['by'] ?? null) === 'version') {
            if (isset($validated['ids']) || ! empty($validated['parent_id'])) {
                throw ValidationException::withMessages(['by' => 'by: "version" orders the milestones of the project; do not give ids or parent_id with it.']);
            }

            $result = $this->graphql($actor, self::BY_VERSION, ['projectId' => $projectId, 'expectedRevision' => $revision, 'dryRun' => $dryRun])['sortMilestones'];
            $payload = ['ok' => true, 'revision' => $result['revision'], 'moved' => $result['moveCount']];

            if ($dryRun) {
                $payload['dry_run'] = true;
                $payload['moves'] = array_values(array_map(
                    fn (array $row): string => "m-{$row['id']} {$row['version']}: {$row['from']} → {$row['to']}",
                    array_filter($this->graphql($actor, self::PLAN, ['projectId' => $projectId])['milestoneVersionOrder']['order'], fn (array $row): bool => $row['moved']),
                ));
            }

            return ToolResult::json($payload);
        }

        if (! isset($validated['ids'])) {
            throw ValidationException::withMessages(['ids' => 'Give ids (all siblings in the new order) or by: "version".']);
        }

        [$level, $parentId, $kind] = $this->level($validated['parent_id'] ?? null);
        $ids = ToolArguments::ids($validated['ids'], $kind, 'ids');

        $result = $this->graphql($actor, self::SIBLINGS, [
            'projectId' => $projectId,
            'level' => $level,
            'parentId' => $parentId,
            'ids' => array_map('strval', $ids),
            'expectedRevision' => $revision,
            'dryRun' => $dryRun,
        ])['reorderSiblings'];

        return ToolResult::json(['ok' => true, 'revision' => $result['revision'], 'moved' => $result['moveCount']] + ($dryRun ? ['dry_run' => true] : []));
    }

    /**
     * @return array{0: string, 1: string|null, 2: string} Level, parent ID, prefix of the siblings' IDs
     */
    private function level(?string $parentId): array
    {
        if ($parentId === null || $parentId === '') {
            return ['MILESTONES', null, 'm'];
        }

        if (str_starts_with($parentId, 'm-')) {
            return ['TODOS', (string) ToolArguments::id($parentId, 'm', 'parent_id'), 't'];
        }

        if (str_starts_with($parentId, 't-')) {
            return ['TASKS', (string) ToolArguments::id($parentId, 't', 'parent_id'), 'k'];
        }

        throw ValidationException::withMessages(['parent_id' => __('ordering.parent_missing')]);
    }
}
