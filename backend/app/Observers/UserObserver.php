<?php

namespace App\Observers;

use App\Models\User;
use App\Services\UserAvatar;

/**
 * Removes the avatar file of a deleted user.
 */
class UserObserver
{
    public function __construct(private readonly UserAvatar $avatar) {}

    public function deleted(User $user): void
    {
        $this->avatar->deleteFileAfterCommit($user->avatar_path);
    }
}
