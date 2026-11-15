<?php

namespace App\Support;

use App\Enums\ProjectRole;
use App\Enums\Right;
use App\Enums\UserRole;

/**
 * The computed overview role × right. Read from the same tables the
 * policies use, so the page can never show other rights than the app
 * enforces.
 */
final class PermissionMatrix
{
    /**
     * @return array{system: list<array{right: Right, roles: list<UserRole>}>, project: list<array{right: Right, roles: list<ProjectRole>}>}
     */
    public static function build(): array
    {
        return [
            'system' => array_map(
                fn (Right $right): array => [
                    'right' => $right,
                    'roles' => array_values(array_filter(
                        UserRole::cases(),
                        fn (UserRole $role): bool => in_array($right, Rights::systemRightsOf($role), true),
                    )),
                ],
                Right::ofScope(Right::SCOPE_SYSTEM),
            ),
            'project' => array_map(
                fn (Right $right): array => [
                    'right' => $right,
                    'roles' => array_values(array_filter(
                        ProjectRole::cases(),
                        fn (ProjectRole $role): bool => Rights::projectRoleAllows($role, $right),
                    )),
                ],
                Right::ofScope(Right::SCOPE_PROJECT),
            ),
        ];
    }
}
