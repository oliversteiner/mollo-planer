<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_project')]
#[Description('Creates a project (admins only) and adds its members by e-mail with a role. The creator does not become a member automatically: add yourself if you want to work in it. Members must already have an account. Returns the new project id for create_project_plan.')]
class CreateProjectTool extends Tool
{
    use ResolvesPeople;
    use RunsGraphQL;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'locale' => ['nullable', 'in:DE,EN'],
            'members' => ['array', 'max:50'],
            'members.*.email' => ['required', 'email'],
            'members.*.role' => ['required', 'in:OWNER,MANAGER,MEMBER'],
        ], [
            'locale.in' => 'locale is DE or EN (language of the notification e-mails).',
            'members.*.role.in' => 'role is OWNER, MANAGER or MEMBER.',
        ]);

        return $this->respond(fn () => DB::transaction(function () use ($validated) {
            $members = $validated['members'] ?? [];
            $ids = $members === [] ? [] : $this->userIdsByEmail();

            $project = $this->operation('CreateProject', [
                'input' => [
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'locale' => $validated['locale'] ?? 'DE',
                ],
            ])['createProject'];

            foreach ($members as $member) {
                $this->operation('SetProjectMember', [
                    'p' => $project['id'],
                    'u' => $this->idForEmail($ids, $member['email'], 'has an account. An admin must invite them in the app first.'),
                    'r' => $member['role'],
                ]);
            }

            return Response::json(['project' => $project, 'members_added' => count($members)]);
        }));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Name of the project.')->required(),
            'description' => $schema->string()->description('Short plain-text description.'),
            'locale' => $schema->string()->enum(['DE', 'EN'])->description('Language of the notification e-mails. Default DE.'),
            'members' => $schema->array()->items($schema->object([
                'email' => $schema->string()->description('E-mail of an existing account.')->required(),
                'role' => $schema->string()->enum(['OWNER', 'MANAGER', 'MEMBER'])->description('OWNER and MANAGER manage the project and create tasks; MEMBER works on tasks.')->required(),
            ]))->description('People to add, with their role.'),
        ];
    }
}
