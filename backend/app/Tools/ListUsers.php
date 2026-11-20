<?php

namespace App\Tools;

use App\Models\User;
use App\Services\UserDirectory;
use App\Services\UserDirectoryRefused;

class ListUsers extends Tool
{
    public function name(): string
    {
        return 'list_users';
    }

    public function description(): string
    {
        return 'Lists people with an account. With project_id: the members of that project (id, name, e-mail, role). Without it: all accounts (with e-mail) if you manage users, otherwise only names. Use it to look up who can be assigned. People without an account must be invited by an admin in the app first.';
    }

    public function readOnly(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return Schema::input(['project_id' => Schema::string('A project of list_projects: only its members, with e-mail.')]);
    }

    public function handle(User $actor, array $arguments): ToolResult
    {
        $projectId = $arguments['project_id'] ?? null;

        try {
            return ToolResult::json(app(UserDirectory::class)->listFor($actor, $projectId === '' ? null : $projectId));
        } catch (UserDirectoryRefused $refused) {
            return ToolResult::error($refused->getMessage());
        }
    }
}
