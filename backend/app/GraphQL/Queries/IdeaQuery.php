<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Idea;
use Illuminate\Support\Facades\Gate;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Query.idea: one idea, or null when it does not exist or the viewer may not read it.
 */
class IdeaQuery
{
    use ResolvesViewer;

    /**
     * @param  array{id: int|string}  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): ?Idea
    {
        $idea = Idea::query()->find($args['id']);

        return $idea !== null && Gate::forUser($this->viewer($context))->allows('view', $idea) ? $idea : null;
    }
}
