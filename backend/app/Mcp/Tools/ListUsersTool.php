<?php

namespace App\Mcp\Tools;

use App\Enums\Right;
use App\Mcp\Support\McpCursor;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Rights;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
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
#[Description('Lists users with accounts. With project_id: project members (id, name, email, role). Without it: all accounts with email if allowed, else names only.')]
#[IsReadOnly]
class ListUsersTool extends Tool
{
    public function handle(Request $request): Response
    {
        $viewer = $request->user();

        if (! $viewer instanceof User) {
            return Response::error('Not signed in.');
        }

        $validated = $request->validate([
            'project_id' => ['sometimes', 'nullable'],
            'query' => ['sometimes', 'nullable', 'string', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:256'],
        ]);

        $projectId = $validated['project_id'] ?? null;
        $search = trim((string) ($validated['query'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 30);
        $cursor = McpCursor::decode($validated['cursor'] ?? null, ['name', 'id']);
        $canManageUsers = Rights::allows($viewer, Right::USERS_MANAGE);

        if ($projectId !== null && $projectId !== '') {
            $project = Project::query()->visibleTo($viewer)->find($projectId);

            if ($project === null) {
                return Response::error("Project {$projectId} not found.");
            }

            $members = $project->members()
                ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                }))
                ->when($cursor !== null, fn (Builder $q) => $q->where(function (Builder $sub) use ($cursor): void {
                    $sub->where('name', '>', (string) $cursor['name'])
                        ->orWhere(fn (Builder $sameName) => $sameName->where('name', (string) $cursor['name'])->where('users.id', '>', (int) $cursor['id']));
                }))
                ->orderBy('users.name')
                ->orderBy('users.id')
                ->limit($limit + 1)
                ->get();

            $hasMore = $members->count() > $limit;
            $members = $members->take($limit)->values();
            $last = $members->last();

            return Response::text(json_encode([
                'data' => $members->map(fn (User $member): array => [
                    'id' => (string) $member->getKey(),
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => Role::cached($member->membership->role_id)?->key,
                ])->all(),
                'next_cursor' => $hasMore && $last instanceof User ? McpCursor::encode(['name' => $last->name, 'id' => (int) $last->getKey()]) : null,
                'has_more' => $hasMore,
            ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));
        }

        $accounts = User::query()
            ->whereNotNull('approved_at')
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $sub) use ($search, $canManageUsers): void {
                $sub->where('name', 'like', "%{$search}%");
                if ($canManageUsers) {
                    $sub->orWhere('email', 'like', "%{$search}%");
                }
            }))
            ->when($cursor !== null, fn (Builder $q) => $q->where(function (Builder $sub) use ($cursor): void {
                $sub->where('name', '>', (string) $cursor['name'])
                    ->orWhere(fn (Builder $sameName) => $sameName->where('name', (string) $cursor['name'])->where('users.id', '>', (int) $cursor['id']));
            }))
            ->orderBy('name')
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $accounts->count() > $limit;
        $accounts = $accounts->take($limit)->values();
        $last = $accounts->last();

        $payload = $canManageUsers
            ? $accounts->map(fn (User $user): array => [
                'id' => (string) $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'role' => Role::cached($user->role_id)?->key,
            ])->all()
            : $accounts->map(fn (User $user): array => ['name' => $user->name])->all();

        return Response::text(json_encode([
            'data' => $payload,
            'next_cursor' => $hasMore && $last instanceof User ? McpCursor::encode(['name' => $last->name, 'id' => (int) $last->getKey()]) : null,
            'has_more' => $hasMore,
        ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Project ID (from list_projects) to list its members with email.'),
            'query' => $schema->string()->description('Optional name/email filter.'),
            'limit' => $schema->integer()->description('Max results (1-100). Default 30.'),
            'cursor' => $schema->string()->description('next_cursor from the previous page.'),
        ];
    }
}
