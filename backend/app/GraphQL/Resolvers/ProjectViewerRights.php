<?php

namespace App\GraphQL\Resolvers;

use App\Enums\Right;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Support\Rights;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Project.viewerRights: what the viewer may do in this project.
 */
class ProjectViewerRights
{
    use ResolvesViewer;

    /**
     * @param  array<string, mixed>  $args
     * @return list<Right>
     */
    public function __invoke(Project $project, array $args, GraphQLContext $context): array
    {
        return Rights::rightsOf($this->viewer($context), $project);
    }
}
