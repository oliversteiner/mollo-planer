<?php

namespace App\Services;

use App\Enums\Right;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Rights;

/**
 * Who has an account, as the tool list_users shows it (task 34): the members of a project the person may see, or all
 * accounts (with e-mail for people who manage users, names only for everybody else). In a service so that the
 * assistant reads the same people the same way.
 */
class UserDirectory
{
    /**
     * @return list<array<string, mixed>>
     *
     * @throws UserDirectoryRefused when the project does not exist or the person may not see it
     */
    public function listFor(User $viewer, ?string $projectId = null): array
    {
        if ($projectId !== null && $projectId !== '') {
            $project = Project::query()->visibleTo($viewer)->find($projectId);

            if ($project === null) {
                throw new UserDirectoryRefused("Project {$projectId} not found.");
            }

            return $project->members()->orderBy('name')->get()->map(fn (User $member): array => [
                'id' => (string) $member->getKey(),
                'name' => $member->name,
                'email' => $member->email,
                'role' => Role::cached($member->membership->role_id)?->key,
            ])->all();
        }

        $accounts = User::query()->whereNotNull('approved_at')->orderBy('name')->get();

        if (Rights::allows($viewer, Right::USERS_MANAGE)) {
            return $accounts->map(fn (User $user): array => [
                'id' => (string) $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'role' => Role::cached($user->role_id)?->key,
            ])->all();
        }

        return $accounts->map(fn (User $user): array => ['name' => $user->name])->all();
    }
}
