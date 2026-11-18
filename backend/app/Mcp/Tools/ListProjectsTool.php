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
#[Description('Lists the projects you can see, with your role, what you may do there (abilities), the progress and the members (name, e-mail, role). Use it first to find a project id or the people of a project. Archived projects (read only) are left out unless you ask for them with archived: true.')]
#[IsReadOnly]
class ListProjectsTool extends Tool
{
    use RunsGraphQL;

    public function handle(Request $request): Response
    {
        $validated = $request->validate(
            ['archived' => ['sometimes', 'boolean']],
            ['archived.boolean' => 'archived is true (only archived projects) or false (the others, default).'],
        );

        return $this->respond(fn () => Response::json(
            $this->operation('ListProjects', ['archived' => (bool) ($validated['archived'] ?? false)])['projects'] ?? [],
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'archived' => $schema->boolean()->description('true: only archived projects (read only). Default false: the others.'),
        ];
    }
}
