<?php

namespace App\Policies;

use App\Enums\ProjectAbility;
use App\Models\Comment;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAbilities;
use App\Support\ProjectPermissions;

class CommentPolicy
{
    use ChecksProjectAbilities;

    /**
     * Only the author edits a comment, and only while they can still see
     * the project. Nobody edits someone else's comment, not even an admin.
     */
    public function update(User $user, Comment $comment): bool
    {
        return $this->isAuthorWithAccess($user, $comment);
    }

    /**
     * The author deletes their own comment. Someone else's comment needs
     * the ability to moderate (owner, manager, admin).
     */
    public function delete(User $user, Comment $comment): bool
    {
        return $this->isAuthorWithAccess($user, $comment)
            || $this->allows($user, $comment, ProjectAbility::COMMENT_MODERATE);
    }

    private function isAuthorWithAccess(User $user, Comment $comment): bool
    {
        return $comment->isAuthoredBy($user)
            && ProjectPermissions::canView($user, $comment->owningProject());
    }
}
