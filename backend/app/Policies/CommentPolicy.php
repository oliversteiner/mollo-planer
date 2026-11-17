<?php

namespace App\Policies;

use App\Enums\Right;
use App\Models\Comment;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAbilities;
use App\Support\Rights;

class CommentPolicy
{
    use ChecksProjectAbilities;

    /**
     * Only the author edits a comment, and only while they can still see
     * the project and it is not archived. Nobody edits someone else's
     * comment, not even an admin.
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
            || $this->allows($user, $comment, Right::COMMENT_MODERATE);
    }

    private function isAuthorWithAccess(User $user, Comment $comment): bool
    {
        $project = $comment->owningProject();

        if (! $comment->isAuthoredBy($user) || ! Rights::canView($user, $project)) {
            return false;
        }

        if ($project->isArchived()) {
            $this->refuseAsArchived();
        }

        return true;
    }
}
