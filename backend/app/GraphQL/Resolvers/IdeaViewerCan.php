<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Idea;
use Illuminate\Support\Facades\Gate;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Idea.viewerCanUpdate: only the author changes an idea.
 */
class IdeaViewerCan
{
    use ResolvesViewer;

    public function update(Idea $idea, array $args, GraphQLContext $context): bool
    {
        return Gate::forUser($this->viewer($context))->allows('update', $idea);
    }
}
