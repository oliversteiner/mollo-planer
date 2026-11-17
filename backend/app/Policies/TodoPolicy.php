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

    /** Back from the trash (in an archived project too). */
    public function restore(User $user, Todo $todo): bool
    {
        return $this->allows($user, $todo, Right::TRASH_RESTORE);
    }

    /** Out of the trash for good. */
    public function forceDelete(User $user, Todo $todo): bool
    {
        return $this->allows($user, $todo, Right::TRASH_PURGE);
    }
}
