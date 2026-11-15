<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Models\Role;
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
    public function __invoke(Project $project, array $args, GraphQLContext $context): ?Role
    {
        return $project->roleOf($this->viewer($context));
    }
}
