<?php

namespace App\Policies;

use App\Enums\Right;
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
        return Rights::allows($user, Right::PROJECTS_CREATE);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::PROJECT_UPDATE);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::PROJECT_DELETE);
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::MEMBERS_MANAGE);
    }

    public function createMilestone(User $user, Project $project): bool
    {
        return $this->allows($user, $project, Right::MILESTONE_CREATE);
    }
}
