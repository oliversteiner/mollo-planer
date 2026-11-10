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
#[Description('Lists all people with an account (id, name, e-mail, role, job function). Only for users who manage users (admin role). Use it to look up accounts. People without an account must be invited by an admin in the app first.')]
#[IsReadOnly]
class ListUsersTool extends Tool
{
    use RunsGraphQL;

    public function handle(Request $request): Response
    {
        return $this->respond(fn () => Response::json($this->graphQL('{ users { id name email role jobFunction } }')['users'] ?? []));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
