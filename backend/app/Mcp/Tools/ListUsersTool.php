<?php

namespace App\Mcp\Tools;

use App\Enums\Right;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The people an AI client may name (docs/assistent-ideen-konzept.md §8): with a project the members of it (name,
 * e-mail, role); without one all accounts with e-mail for whoever may manage users, and names only for everybody
 * else. Assigning is checked on import anyway.
 */
#[Name('list_users')]
#[Description('Lists people with an account. With project_id: the members of that project (id, name, e-mail, role). Without it: all accounts (with e-mail) if you manage users, otherwise only names. Use it to look up who can be assigned. People without an account must be invited by an admin in the app first.')]
#[IsReadOnly]
class ListUsersTool extends Tool
{
    public function handle(Request $request): Response
    {
        $viewer = $request->user();

        if (! $viewer instanceof User) {
            return Response::error('Not signed in.');
        }

        $projectId = $request->get('project_id');

        if ($projectId !== null && $projectId !== '') {
            $project = Project::query()->visibleTo($viewer)->find($projectId);

            if ($project === null) {
                return Response::error("Project {$projectId} not found.");
            }

            return Response::json($project->members()->orderBy('name')->get()->map(fn (User $member): array => [
                'id' => (string) $member->getKey(),
                'name' => $member->name,
                'email' => $member->email,
                'role' => Role::cached($member->membership->role_id)?->key,
            ])->all());
        }

        $accounts = User::query()->whereNotNull('approved_at')->orderBy('name')->get();

        if (Rights::allows($viewer, Right::USERS_MANAGE)) {
            return Response::json($accounts->map(fn (User $user): array => [
                'id' => (string) $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'role' => Role::cached($user->role_id)?->key,
            ])->all());
        }

        return Response::json($accounts->map(fn (User $user): array => ['name' => $user->name])->all());
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return ['project_id' => $schema->string()->description('A project of list_projects: only its members, with e-mail.')];
    }
}
