<?php

namespace App\Policies;

use App\Enums\Right;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAbilities;

class TaskPolicy
{
    use ChecksProjectAbilities;

    /**
     * Everyone with the right may change any task. Someone with only
     * "task.updateOwn" may change the tasks they created themselves.
     */
    public function update(User $user, Task $task): bool
    {
        if ($this->allows($user, $task, Right::TASK_UPDATE)) {
            return true;
        }

        return (string) $task->created_by === (string) $user->getKey()
            && $this->allows($user, $task, Right::TASK_UPDATE_OWN);
    }

    /**
     * The mutation checks the target person (see Rights::mayAssignTask):
     * with only "task.assignSelf" the person may assign the task to themselves.
     */
    public function assign(User $user, Task $task): bool
    {
        return $this->allows($user, $task, Right::TASK_ASSIGN)
            || $this->allows($user, $task, Right::TASK_ASSIGN_SELF);
    }

    public function move(User $user, Task $task): bool
    {
        return $this->allows($user, $task, Right::TASK_MOVE);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->allows($user, $task, Right::TASK_DELETE);
    }

    public function createComment(User $user, Task $task): bool
    {
        return $this->allows($user, $task, Right::COMMENT_CREATE);
    }
}
