<?php

namespace App\GraphQL\Queries;

use App\Models\RoleFunction;
use Illuminate\Database\Eloquent\Collection;

/**
 * Query.roleFunctions: the functions roles are made of, for the settings
 * (right roles.manage, checked by @can in the schema).
 */
class RoleFunctions
{
    /**
     * @param  array{scope?: string|null}  $args
     * @return Collection<int, RoleFunction>
     */
    public function __invoke(mixed $root, array $args): Collection
    {
        $scope = $args['scope'] ?? null;

        return RoleFunction::query()
            ->when($scope !== null, fn ($functions) => $functions->where('scope', $scope))
            ->orderBy('id')
            ->get();
    }
}
