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

#[Name('list_users')]
#[Description('Lists all people with an account (id, name, e-mail). Only for admins and for people who manage members in a project. Use it to find someone by name before adding them to a project. People without an account must be invited by an admin in the app first.')]
#[IsReadOnly]
class ListUsersTool extends Tool
{
    use RunsGraphQL;

    public function handle(Request $request): Response
    {
        return $this->respond(fn () => Response::json($this->graphQL('{ users { id name email isAdmin } }')['users'] ?? []));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
