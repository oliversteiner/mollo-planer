<?php

namespace App\Policies;

use App\Enums\ProjectAbility;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAbilities;

class TaskPolicy
{
    use ChecksProjectAbilities;

    public function update(User $user, Task $task): bool
    {
        return $this->allows($user, $task, ProjectAbility::TaskUpdate);
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->allows($user, $task, ProjectAbility::TaskAssign);
    }

    public function move(User $user, Task $task): bool
    {
        return $this->allows($user, $task, ProjectAbility::TaskMove);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->allows($user, $task, ProjectAbility::TaskDelete);
    }

    public function createComment(User $user, Task $task): bool
    {
        return $this->allows($user, $task, ProjectAbility::CommentCreate);
    }
}
