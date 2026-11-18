<?php

namespace App\GraphQL\Queries;

use App\Models\Role;

/**
 * Query.roles: the roles people and members can be given. Everybody signed
 * in may read them, they hold names and rights only.
 */
class Roles
{
    /**
     * @param  array{scope?: string|null}  $args
     * @return list<Role>
     */
    public function __invoke(mixed $root, array $args): array
    {
        $scope = $args['scope'] ?? null;

        return $scope === null ? array_values(Role::registry()) : Role::ofScope($scope);
    }
}
