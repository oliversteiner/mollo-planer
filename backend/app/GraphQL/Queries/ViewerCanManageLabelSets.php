<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\LabelSet;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * May the viewer create and duplicate label sets? Answered by the LabelSetPolicy.
 */
class ViewerCanManageLabelSets
{
    use ResolvesViewer;

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): bool
    {
        return $this->viewer($context)->can('create', LabelSet::class);
    }
}
