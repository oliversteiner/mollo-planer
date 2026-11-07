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
        return $this->allows($user, $task, ProjectAbility::TASK_UPDATE);
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->allows($user, $task, ProjectAbility::TASK_ASSIGN);
    }

    public function move(User $user, Task $task): bool
    {
        return $this->allows($user, $task, ProjectAbility::TASK_MOVE);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->allows($user, $task, ProjectAbility::TASK_DELETE);
    }

    public function createComment(User $user, Task $task): bool
    {
        return $this->allows($user, $task, ProjectAbility::COMMENT_CREATE);
    }
}
