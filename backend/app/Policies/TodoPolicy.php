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
        return $this->allows($user, $todo, ProjectAbility::TodoUpdate);
    }

    public function delete(User $user, Todo $todo): bool
    {
        return $this->allows($user, $todo, ProjectAbility::TodoDelete);
    }

    public function createTask(User $user, Todo $todo): bool
    {
        return $this->allows($user, $todo, ProjectAbility::TaskCreate);
    }
}
