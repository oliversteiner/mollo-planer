<?php

namespace App\Policies;

use App\Enums\ProjectAbility;
use App\Models\User;
use App\Support\ProjectPermissions;

/**
 * Managing users is for admins only. The user list is also open to
 * everyone who may manage members in at least one project, so they can
 * pick people to add.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return ProjectPermissions::allowsInAnyProject($user, ProjectAbility::MEMBERS_MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Also used by resetUserTwoFactor. An admin cannot remove their own
     * admin flag, so the last admin cannot lock themselves out.
     *
     * @param  array<string, mixed>  $input  Mutation input (Lighthouse injectArgs), snake_case keys
     */
    public function update(User $user, User $target, array $input = []): bool
    {
        if (! $user->is_admin) {
            return false;
        }

        $removesOwnAdmin = $user->is($target)
            && array_key_exists('is_admin', $input)
            && ! $input['is_admin'];

        return ! $removesOwnAdmin;
    }

    /**
     * Everyone removes their own avatar; admins also remove someone else's
     * (for example an unsuitable picture). Uploading is only for oneself.
     */
    public function deleteAvatar(User $user, User $target): bool
    {
        return $user->is($target) || $user->is_admin;
    }

    /**
     * An admin cannot delete their own account.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->is_admin && ! $user->is($target);
    }
}
