<?php

namespace App\GraphQL\Resolvers;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;

/**
 * Project.members: users with their role, sorted by name.
 */
class ProjectMembers
{
    /**
     * @return list<array{user: User, role: Role|null}>
     */
    public function __invoke(Project $project): array
    {
        return $project->members
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (User $user) => ['user' => $user, 'role' => $user->membership->role])
            ->values()
            ->all();
    }
}
