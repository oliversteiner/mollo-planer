<?php

namespace App\Policies;

use App\Enums\Right;
use App\Models\Todo;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAbilities;

class TodoPolicy
{
    use ChecksProjectAbilities;

    public function update(User $user, Todo $todo): bool
    {
        return $this->allows($user, $todo, Right::TODO_UPDATE);
    }

    public function delete(User $user, Todo $todo): bool
    {
        return $this->allows($user, $todo, Right::TODO_DELETE);
    }

    public function createTask(User $user, Todo $todo): bool
    {
        return $this->allows($user, $todo, Right::TASK_CREATE);
    }
}
