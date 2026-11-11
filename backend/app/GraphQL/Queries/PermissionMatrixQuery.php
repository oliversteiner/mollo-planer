<?php

namespace App\GraphQL\Queries;

use App\Support\PermissionMatrix;

/**
 * The rights table (admins only, checked by @can in the schema).
 */
class PermissionMatrixQuery
{
    /**
     * @param  array<string, mixed>  $args
     * @return array{system: list<array<string, mixed>>, project: list<array<string, mixed>>}
     */
    public function __invoke(mixed $root, array $args): array
    {
        return PermissionMatrix::build();
    }
}
