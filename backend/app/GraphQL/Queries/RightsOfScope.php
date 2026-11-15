<?php

namespace App\GraphQL\Queries;

use App\Enums\Right;

/**
 * Query.rights: the rights of one scope, in the order of the enum. The list
 * is fixed by the code, so everybody signed in may read it.
 */
class RightsOfScope
{
    /**
     * @param  array{scope: string}  $args
     * @return list<Right>
     */
    public function __invoke(mixed $root, array $args): array
    {
        return Right::ofScope($args['scope']);
    }
}
