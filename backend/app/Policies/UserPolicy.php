<?php

namespace App\Policies;

use App\Enums\SystemAbility;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\SystemPermissions;

/**
 * Managing users is for users with users.manage (the admin role). The full
 * user list is for them too. Everyone who manages members somewhere uses the
 * narrow member search instead, which shows only names.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return SystemPermissions::allows($user, SystemAbility::USERS_MANAGE);
    }

    /**
     * Whether someone has a second factor (2FA app, passkey): the person
     * themselves and users who manage users (for resetting it). Others must
     * not learn it.
     */
    public function viewSecurity(User $user, User $target): bool
    {
        return $user->is($target) || SystemPermissions::allows($user, SystemAbility::USERS_MANAGE);
    }

    public function create(User $user): bool
    {
        return SystemPermissions::allows($user, SystemAbility::USERS_MANAGE);
    }

    /**
     * Also used by resetUserTwoFactor. Nobody can take the admin role away
     * from themselves, so the last admin cannot lock themselves out.
     *
     * @param  array<string, mixed>  $input  Mutation input (Lighthouse injectArgs), snake_case keys
     */
    public function update(User $user, User $target, array $input = []): bool
    {
        if (! SystemPermissions::allows($user, SystemAbility::USERS_MANAGE)) {
            return false;
        }

        $removesOwnAdminRole = $user->is($target)
            && array_key_exists('role', $input)
            && $this->roleOf($input['role']) !== UserRole::ADMIN;

        return ! $removesOwnAdminRole;
    }

    /**
     * Everyone removes their own avatar; users who manage users also remove
     * someone else's (for example an unsuitable picture). Uploading is only
     * for oneself.
     */
    public function deleteAvatar(User $user, User $target): bool
    {
        return $user->is($target) || SystemPermissions::allows($user, SystemAbility::USERS_MANAGE);
    }

    /**
     * Settings of the whole application (for example two-factor
     * authentication) are for users who manage users.
     */
    public function updateAppSettings(User $user): bool
    {
        return SystemPermissions::allows($user, SystemAbility::USERS_MANAGE);
    }

    /**
     * Nobody deletes their own account.
     */
    public function delete(User $user, User $target): bool
    {
        return SystemPermissions::allows($user, SystemAbility::USERS_MANAGE) && ! $user->is($target);
    }

    /**
     * The mutation input may carry the role as enum case or as its value.
     */
    private function roleOf(mixed $value): ?UserRole
    {
        return $value instanceof UserRole ? $value : UserRole::tryFrom((string) $value);
    }
}
