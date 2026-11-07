<?php

namespace App\Policies;

use App\Enums\ProjectAbility;
use App\Models\Todo;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAbilities;

class TodoPolicy
{
    use ChecksProjectAbilities;

    public function update(User $user, Todo $todo): bool
    {
        return $this->allows($user, $todo, ProjectAbility::TODO_UPDATE);
    }

    public function delete(User $user, Todo $todo): bool
    {
        return $this->allows($user, $todo, ProjectAbility::TODO_DELETE);
    }

    public function createTask(User $user, Todo $todo): bool
    {
        return $this->allows($user, $todo, ProjectAbility::TASK_CREATE);
    }
}
