<?php

namespace App\Support;

use App\Enums\SystemAbility;
use App\Enums\UserRole;
use App\Models\User;

/**
 * The single source of truth for global (system-wide) permissions.
 *
 * Policies ask only this class. No other code compares roles. To restrict
 * or extend an ability, change its line in ABILITIES_BY_ROLE. The admin role
 * has every ability and is not listed there.
 */
final class SystemPermissions
{
    /**
     * @var array<string, list<SystemAbility>>
     */
    private const ABILITIES_BY_ROLE = [
        'projektleitung' => [SystemAbility::PROJECTS_CREATE, SystemAbility::LABEL_SETS_MANAGE],
        'koordination' => [SystemAbility::PROJECTS_CREATE],
        'mitglied' => [],
        'lesend' => [],
    ];

    /**
     * Abilities of a role, in the order of the enum.
     *
     * @return list<SystemAbility>
     */
    public static function abilitiesOf(?UserRole $role): array
    {
        if ($role === UserRole::ADMIN) {
            return SystemAbility::cases();
        }

        if ($role === null) {
            return [];
        }

        return self::ABILITIES_BY_ROLE[$role->value] ?? [];
    }

    /**
     * Does this user have the system-wide ability? Guests have none.
     */
    public static function allows(?User $user, SystemAbility $ability): bool
    {
        if ($user === null) {
            return false;
        }

        return in_array($ability, self::abilitiesOf($user->role), true);
    }

    /**
     * Read-only users may not change anything, whatever their project role.
     */
    public static function isReadOnly(User $user): bool
    {
        return $user->role === UserRole::LESEND;
    }
}
