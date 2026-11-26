<?php

namespace App\Tools;

use App\Models\User;

class ListProjects extends Tool
{
    public function name(): string
    {
        return 'list_projects';
    }

    public function description(): string
    {
        return 'Lists visible projects (id, name, progress, role, revision). Archived ones only with archived: true.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return Schema::input([
            'archived' => Schema::boolean('Only archived projects.'),
            'include_members' => Schema::boolean('Add the members.'),
            'include_rights' => Schema::boolean('Add your rights.'),
        ]);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate(
            $arguments,
            [
                'archived' => ['sometimes', 'boolean'],
                'include_members' => ['sometimes', 'boolean'],
                'include_rights' => ['sometimes', 'boolean'],
            ],
            ['archived.boolean' => 'archived must be true or false.'],
        );

        $includeMembers = (bool) ($validated['include_members'] ?? false);
        $includeRights = (bool) ($validated['include_rights'] ?? false);

        $projects = $this->operation($actor, 'ListProjects', [
            'archived' => (bool) ($validated['archived'] ?? false),
            'includeMembers' => $includeMembers,
            'includeRights' => $includeRights,
        ])['projects'] ?? [];

        return ToolResult::json(array_map(function (array $project) use ($includeMembers, $includeRights): array {
            $item = [
                'id' => $project['id'] ?? null,
                'name' => $project['name'] ?? null,
                'locale' => $project['locale'] ?? null,
                'archived_at' => $project['archivedAt'] ?? null,
                'planer_revision' => $project['planerRevision'] ?? null,
                'progress' => $project['progress'] ?? null,
                'role' => $project['viewerRole']['key'] ?? null,
            ];

            if ($includeMembers && isset($project['members'])) {
                $item['members'] = array_map(fn (array $member): array => [
                    'id' => $member['user']['id'] ?? null,
                    'name' => $member['user']['name'] ?? null,
                    'email' => $member['user']['email'] ?? null,
                    'role' => $member['role']['key'] ?? null,
                ], $project['members']);
            }

            if ($includeRights && isset($project['viewerRights'])) {
                $item['abilities'] = $project['viewerRights'];
            }

            return $item;
        }, $projects));
    }
}
