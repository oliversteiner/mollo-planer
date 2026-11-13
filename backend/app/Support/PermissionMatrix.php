<?php

namespace App\Support;

use App\Enums\ProjectAbility;
use App\Enums\ProjectRole;
use App\Enums\SystemAbility;
use App\Enums\UserRole;

/**
 * The rights table for admins: which role has which ability. Read from the
 * same tables the policies use, so the page can never show other rights than
 * the app enforces.
 */
final class PermissionMatrix
{
    /**
     * @return array{system: list<array{ability: SystemAbility, roles: list<UserRole>}>, project: list<array{ability: ProjectAbility, roles: list<ProjectRole>}>}
     */
    public static function build(): array
    {
        return [
            'system' => array_map(
                fn (SystemAbility $ability): array => [
                    'ability' => $ability,
                    'roles' => array_values(array_filter(
                        UserRole::cases(),
                        fn (UserRole $role): bool => in_array($ability, Rights::systemRightsOf($role), true),
                    )),
                ],
                SystemAbility::cases(),
            ),
            'project' => array_map(
                fn (ProjectAbility $ability): array => [
                    'ability' => $ability,
                    'roles' => array_values(array_filter(
                        ProjectRole::cases(),
                        fn (ProjectRole $role): bool => Rights::projectRoleAllows($role, $ability),
                    )),
                ],
                ProjectAbility::cases(),
            ),
        ];
    }
}
