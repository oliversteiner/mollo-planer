<?php

namespace App\Policies;

use App\Enums\ProjectAbility;
use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAbilities;

class ProjectPolicy
{
    use ChecksProjectAbilities;

    /**
     * Only admins create projects.
     */
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Project $project): bool
    {
        return $this->allows($user, $project, ProjectAbility::ProjectUpdate);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->allows($user, $project, ProjectAbility::ProjectDelete);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->allows($user, $project, ProjectAbility::MembersManage);
    }

    public function createMilestone(User $user, Project $project): bool
    {
        return $this->allows($user, $project, ProjectAbility::MilestoneCreate);
    }
}
