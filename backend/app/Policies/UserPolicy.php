<?php

namespace App\Policies;

use App\Enums\Right;
use App\Models\Role;
use App\Models\User;
use App\Support\Rights;

/**
 * Managing users is for users with the right users.manage. The full
 * user list is for them too. Everyone who manages members somewhere uses the
 * narrow member search instead, which shows only names.
 */
class UserPolicy
{
    /** Rights that somebody must always keep, so the settings stay reachable. */
    private const PROTECTED_RIGHTS = [Right::USERS_MANAGE, Right::ROLES_MANAGE];

    public function viewAny(User $user): bool
    {
        return Rights::allows($user, Right::USERS_MANAGE);
    }

    /**
     * Whether someone has a second factor (2FA app, passkey): the person
     * themselves and users who manage users (for resetting it). Others must
     * not learn it.
     */
    public function viewSecurity(User $user, User $target): bool
    {
        return $user->is($target) || Rights::allows($user, Right::USERS_MANAGE);
    }

    public function create(User $user): bool
    {
        return Rights::allows($user, Right::USERS_MANAGE);
    }

    /**
     * Also used by resetUserTwoFactor. Two rules protect against locking
     * everybody out (docs/rollen-funktionen-rechte.md, §7.1 and §7.2):
     * nobody takes users.manage or roles.manage away from themselves, and
     * the last person with one of these rights keeps it.
     *
     * @param  array<string, mixed>  $input  Mutation input (Lighthouse injectArgs), snake_case keys
     */
    public function update(User $user, User $target, array $input = []): bool
    {
        if (! Rights::allows($user, Right::USERS_MANAGE)) {
            return false;
        }

        if (! array_key_exists('role', $input)) {
            return true;
        }

        $newRights = Rights::rightsOfRole(Role::resolve(Role::SCOPE_SYSTEM, $input['role']));

        foreach (self::PROTECTED_RIGHTS as $right) {
            $loses = Rights::allows($target, $right) && ! in_array($right, $newRights, true);

            if ($loses && ($user->is($target) || Rights::countUsersWith($right, $target) === 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Everyone removes their own avatar; users who manage users also remove
     * someone else's (for example an unsuitable picture). Uploading is only
     * for oneself.
     */
    public function deleteAvatar(User $user, User $target): bool
    {
        return $user->is($target) || Rights::allows($user, Right::USERS_MANAGE);
    }

    /**
     * Settings of the whole application (for example two-factor
     * authentication) need the right settings.manage.
     */
    public function updateAppSettings(User $user): bool
    {
        return Rights::allows($user, Right::SETTINGS_MANAGE);
    }

    /**
     * Roles and functions in the settings (/admin/roles) need the right
     * roles.manage. The ground rules that keep the settings reachable are
     * checked by App\Services\RoleAdministration.
     */
    public function manageRoles(User $user): bool
    {
        return Rights::allows($user, Right::ROLES_MANAGE);
    }

    /**
     * Releases a self-registered account. Only accounts whose mail address is
     * confirmed and that are still waiting can be approved; nobody approves
     * themselves.
     */
    public function approve(User $user, User $target): bool
    {
        return Rights::allows($user, Right::USERS_MANAGE)
            && $target->hasVerifiedEmail()
            && ! $target->isApproved()
            && ! $user->is($target);
    }

    /**
     * Rejects a waiting account and deletes it. Approved accounts are never
     * rejected here; they use deleteUser.
     */
    public function reject(User $user, User $target): bool
    {
        return Rights::allows($user, Right::USERS_MANAGE)
            && ! $target->isApproved()
            && ! $user->is($target);
    }

    /**
     * Nobody deletes their own account, and the last person with
     * users.manage or roles.manage stays (§7.1).
     */
    public function delete(User $user, User $target): bool
    {
        if (! Rights::allows($user, Right::USERS_MANAGE) || $user->is($target)) {
            return false;
        }

        foreach (self::PROTECTED_RIGHTS as $right) {
            if (Rights::allows($target, $right) && Rights::countUsersWith($right, $target) === 0) {
                return false;
            }
        }

        return true;
    }
}
