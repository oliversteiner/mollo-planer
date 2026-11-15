<?php

namespace App\Support;

use App\Enums\Right;
use App\Models\Role;

/**
 * The computed overview role × right. Read from the same tables the
 * policies use, so the page can never show other rights than the app
 * enforces.
 */
final class PermissionMatrix
{
    /**
     * @return array{system: list<array{right: Right, roles: list<Role>}>, project: list<array{right: Right, roles: list<Role>}>}
     */
    public static function build(): array
    {
        return [
            'system' => self::rows(Right::SCOPE_SYSTEM),
            'project' => self::rows(Right::SCOPE_PROJECT),
        ];
    }

    /**
     * @return list<array{right: Right, roles: list<Role>}>
     */
    private static function rows(string $scope): array
    {
        return array_map(
            fn (Right $right): array => [
                'right' => $right,
                'roles' => array_values(array_filter(
                    Role::ofScope($scope),
                    fn (Role $role): bool => Rights::roleAllows($role, $right),
                )),
            ],
            Right::ofScope($scope),
        );
    }
}
