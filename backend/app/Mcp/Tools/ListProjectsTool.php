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
#[Description('Lists the projects you can see, with your role, what you may do there (abilities), the progress and the members (name, e-mail, role). Use it first to find a project id or the people of a project.')]
#[IsReadOnly]
class ListProjectsTool extends Tool
{
    use RunsGraphQL;

    public function handle(Request $request): Response
    {
        return $this->respond(fn () => Response::json($this->operation('ListProjects')['projects'] ?? []));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
