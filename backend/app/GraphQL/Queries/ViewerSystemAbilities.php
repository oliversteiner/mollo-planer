<?php

namespace App\GraphQL\Queries;

use App\Enums\SystemAbility;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Support\SystemPermissions;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * The system-wide abilities of the viewer, from the SystemPermissions table.
 */
class ViewerSystemAbilities
{
    use ResolvesViewer;

    /**
     * @param  array<string, mixed>  $args
     * @return list<SystemAbility>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        return SystemPermissions::abilitiesOf($this->viewer($context)->role);
    }
}
