<?php

namespace App\GraphQL\Queries;

use App\Enums\Right;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Support\Rights;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Query.viewerRights: the system rights of the viewer.
 */
class ViewerRights
{
    use ResolvesViewer;

    /**
     * @param  array<string, mixed>  $args
     * @return list<Right>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        return Rights::rightsOf($this->viewer($context));
    }
}
