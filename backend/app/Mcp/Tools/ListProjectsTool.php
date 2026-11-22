<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_projects')]
#[Description('Lists visible projects compactly (id, name, progress, role). Set include_members or include_rights only when needed. Default archived: false.')]
#[IsReadOnly]
class ListProjectsTool extends Tool
{
    use RunsGraphQL;

    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            [
                'archived' => ['sometimes', 'boolean'],
                'include_members' => ['sometimes', 'boolean'],
                'include_rights' => ['sometimes', 'boolean'],
            ],
            ['archived.boolean' => 'archived must be true or false.'],
        );

        $includeMembers = (bool) ($validated['include_members'] ?? false);
        $includeRights = (bool) ($validated['include_rights'] ?? false);

        return $this->respond(function () use ($validated, $includeMembers, $includeRights): Response {
            $projects = $this->operation('ListProjects', [
                'archived' => (bool) ($validated['archived'] ?? false),
                'includeMembers' => $includeMembers,
                'includeRights' => $includeRights,
            ])['projects'] ?? [];

            $slim = array_map(function (array $project) use ($includeMembers, $includeRights): array {
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
            }, $projects);

            return Response::text(json_encode(
                $slim,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'archived' => $schema->boolean()->description('true: only archived projects (read only). Default false.'),
            'include_members' => $schema->boolean()->description('true: include project members. Default false to save tokens.'),
            'include_rights' => $schema->boolean()->description('true: include viewerRights in project. Default false to save tokens.'),
        ];
    }
}
