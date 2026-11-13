<?php

namespace App\Policies;

use App\Enums\ProjectAbility;
use App\Enums\SystemAbility;
use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAbilities;
use App\Support\Rights;

class ProjectPolicy
{
    use ChecksProjectAbilities;

    /**
     * Who may create projects is decided by the global role (projects.create).
     */
    public function create(User $user): bool
    {
        return Rights::systemAllows($user, SystemAbility::PROJECTS_CREATE);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->allows($user, $project, ProjectAbility::PROJECT_UPDATE);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->allows($user, $project, ProjectAbility::PROJECT_DELETE);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->allows($user, $project, ProjectAbility::MEMBERS_MANAGE);
    }

    public function createMilestone(User $user, Project $project): bool
    {
        return $this->allows($user, $project, ProjectAbility::MILESTONE_CREATE);
    }
}
