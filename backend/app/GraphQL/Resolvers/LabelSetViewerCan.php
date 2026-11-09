<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\LabelSet;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * LabelSet.viewerCanUpdate / viewerCanDelete, answered by the LabelSetPolicy.
 * Deleting is refused anyway while a project uses the set (see DeleteLabelSet).
 */
class LabelSetViewerCan
{
    use ResolvesViewer;

    /**
     * @param  array<string, mixed>  $args
     */
    public function update(LabelSet $set, array $args, GraphQLContext $context): bool
    {
        return $this->viewer($context)->can('update', $set);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function delete(LabelSet $set, array $args, GraphQLContext $context): bool
    {
        return $this->viewer($context)->can('delete', $set);
    }
}
