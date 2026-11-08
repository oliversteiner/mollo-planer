<?php

namespace App\Observers;

use App\Models\User;
use App\Services\UserAvatar;
use App\Support\McpTokens;

/**
 * Removes the avatar file and the access tokens of a deleted user.
 */
class UserObserver
{
    public function __construct(private readonly UserAvatar $avatar) {}

    public function deleted(User $user): void
    {
        $this->avatar->deleteFileAfterCommit($user->avatar_path);
        // Tokens are polymorphic, without a foreign key that would cascade.
        McpTokens::revokeAll($user);
    }
}
