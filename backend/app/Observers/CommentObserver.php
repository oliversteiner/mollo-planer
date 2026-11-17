<?php

namespace App\Observers;

use App\Models\Comment;

/**
 * A comment counts as edited only when its text changes (docs/fix-m2.md
 * F5.6); deleting and restoring leave edited_at alone.
 */
class CommentObserver
{
    public function updating(Comment $comment): void
    {
        if ($comment->isDirty('body')) {
            $comment->edited_at = $comment->freshTimestamp();
        }
    }
}
