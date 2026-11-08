<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('set_project_member')]
#[Description('Adds a person to a project or changes their role. Needs the right to manage members; only owners and admins grant or take the owner role. The person must already have an account.')]
#[IsIdempotent]
class SetProjectMemberTool extends Tool
{
    use ResolvesPeople;
    use RunsGraphQL;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'project_id' => ['required'],
            'email' => ['required', 'email'],
            'role' => ['required', 'in:OWNER,MANAGER,MEMBER'],
        ], ['role.in' => 'role is OWNER, MANAGER or MEMBER.']);

        return $this->respond(function () use ($validated) {
            $userId = $this->idForEmail($this->userIdsByEmail(), $validated['email'], 'has an account. An admin must invite them in the app first.');
            $members = $this->graphQL('mutation ($p: ID!, $u: ID!, $r: ProjectRole!) {
                setProjectMember(projectId: $p, userId: $u, role: $r) { members { role user { name email } } }
            }', ['p' => (string) $validated['project_id'], 'u' => $userId, 'r' => $validated['role']])['setProjectMember']['members'];

            return Response::json(['members' => $members]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Id of the project (from list_projects).')->required(),
            'email' => $schema->string()->description('E-mail of an existing account.')->required(),
            'role' => $schema->string()->enum(['OWNER', 'MANAGER', 'MEMBER'])->required(),
        ];
    }
}
