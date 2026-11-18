<?php

namespace App\Policies;

use App\Enums\Right;
use App\Models\Idea;
use App\Models\User;
use App\Support\Rights;

/**
 * Ideas are private (docs/auftrag-m6.md §2.2): the author reads and changes them; with ideas.viewAny others
 * may read, never change. Creating needs ideas.create.
 */
class IdeaPolicy
{
    public function create(User $user): bool
    {
        return Rights::allows($user, Right::IDEAS_CREATE);
    }

    public function view(User $user, Idea $idea): bool
    {
        return $this->isAuthor($user, $idea) || Rights::allows($user, Right::IDEAS_VIEW_ANY);
    }

    public function update(User $user, Idea $idea): bool
    {
        return $this->isAuthor($user, $idea);
    }

    public function delete(User $user, Idea $idea): bool
    {
        return $this->isAuthor($user, $idea);
    }

    public function restore(User $user, Idea $idea): bool
    {
        return $this->isAuthor($user, $idea);
    }

    public function forceDelete(User $user, Idea $idea): bool
    {
        return $this->isAuthor($user, $idea);
    }

    private function isAuthor(User $user, Idea $idea): bool
    {
        return (int) $idea->user_id === (int) $user->getKey();
    }
}
