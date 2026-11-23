<?php

namespace App\Tools;

use App\Enums\Right;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Rights;
use App\Tools\Support\McpCursor;
use Illuminate\Database\Eloquent\Builder;

/**
 * The people an AI client may name (docs/assistent-ideen-konzept.md §8): with a project the members of it (name,
 * e-mail, role); without one all accounts with e-mail for whoever may manage users, and names only for everybody
 * else. Assigning is checked on import anyway.
 */
class ListUsers extends Tool
{
    public function name(): string
    {
        return 'list_users';
    }

    public function description(): string
    {
        return 'Lists users with accounts. With project_id: project members (id, name, email, role). Without it: all accounts with email if allowed, else names only.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return Schema::input([
            'project_id' => Schema::string('Project ID (from list_projects) to list its members with email.'),
            'query' => Schema::string('Optional name/email filter.'),
            'limit' => Schema::integer('Max results (1-100). Default 30.'),
            'cursor' => Schema::string('next_cursor from the previous page.'),
        ]);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $validated = $this->validate($arguments, [
            'project_id' => ['sometimes', 'nullable'],
            'query' => ['sometimes', 'nullable', 'string', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:256'],
        ]);

        $projectId = $validated['project_id'] ?? null;
        $search = trim((string) ($validated['query'] ?? ''));
        $limit = (int) ($validated['limit'] ?? 30);
        $cursor = McpCursor::decode($validated['cursor'] ?? null, ['name', 'id']);
        $canManageUsers = Rights::allows($actor, Right::USERS_MANAGE);

        if ($projectId !== null && $projectId !== '') {
            $project = Project::query()->visibleTo($actor)->find($projectId);

            if ($project === null) {
                return ToolResult::error("Project {$projectId} not found.");
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

            return ToolResult::json([
                'data' => $members->map(fn (User $member): array => [
                    'id' => (string) $member->getKey(),
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => Role::cached($member->membership->role_id)?->key,
                ])->all(),
                'next_cursor' => $hasMore && $last instanceof User ? McpCursor::encode(['name' => $last->name, 'id' => (int) $last->getKey()]) : null,
                'has_more' => $hasMore,
            ]);
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

        return ToolResult::json([
            'data' => $payload,
            'next_cursor' => $hasMore && $last instanceof User ? McpCursor::encode(['name' => $last->name, 'id' => (int) $last->getKey()]) : null,
            'has_more' => $hasMore,
        ]);
    }
}
