<?php

namespace App\GraphQL\Resolvers;

use App\Enums\ProjectRole;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Project.viewerRole: the viewer's role, null for admins without membership.
 */
class ViewerRole
{
    use ResolvesViewer;

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(Project $project, array $args, GraphQLContext $context): ?ProjectRole
    {
        return $project->roleOf($this->viewer($context));
    }
}
