<?php

namespace App\Policies;

use App\Enums\Right;
use App\Models\Milestone;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAbilities;

class MilestonePolicy
{
    use ChecksProjectAbilities;

    public function update(User $user, Milestone $milestone): bool
    {
        return $this->allows($user, $milestone, Right::MILESTONE_UPDATE);
    }

    public function delete(User $user, Milestone $milestone): bool
    {
        return $this->allows($user, $milestone, Right::MILESTONE_DELETE);
    }

    public function createTodo(User $user, Milestone $milestone): bool
    {
        return $this->allows($user, $milestone, Right::TODO_CREATE);
    }

    /** Back from the trash (in an archived project too). */
    public function restore(User $user, Milestone $milestone): bool
    {
        return $this->allows($user, $milestone, Right::TRASH_RESTORE);
    }

    /** Out of the trash for good. */
    public function forceDelete(User $user, Milestone $milestone): bool
    {
        return $this->allows($user, $milestone, Right::TRASH_PURGE);
    }
}
