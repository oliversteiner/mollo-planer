<?php

namespace App\Policies;

use App\Enums\ProjectAbility;
use App\Models\Milestone;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAbilities;

class MilestonePolicy
{
    use ChecksProjectAbilities;

    public function update(User $user, Milestone $milestone): bool
    {
        return $this->allows($user, $milestone, ProjectAbility::MILESTONE_UPDATE);
    }

    public function delete(User $user, Milestone $milestone): bool
    {
        return $this->allows($user, $milestone, ProjectAbility::MILESTONE_DELETE);
    }

    public function createTodo(User $user, Milestone $milestone): bool
    {
        return $this->allows($user, $milestone, ProjectAbility::TODO_CREATE);
    }
}
