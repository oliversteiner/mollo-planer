<?php

namespace App\GraphQL\Resolvers;

use App\Enums\ProjectAbility;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Support\Rights;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Project.viewerAbilities: what the viewer may do, from the permission table.
 */
class ViewerAbilities
{
    use ResolvesViewer;

    /**
     * @param  array<string, mixed>  $args
     * @return list<ProjectAbility>
     */
    public function __invoke(Project $project, array $args, GraphQLContext $context): array
    {
        return Rights::projectRightsOf($this->viewer($context), $project);
    }
}
