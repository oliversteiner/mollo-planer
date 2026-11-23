<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Services\ProjectStatistics as Statistics;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Query.projectStatistics: the numbers of one project. Whoever may see the project may see them (also an archived
 * one); a project the viewer may not see is refused like any other, without saying whether it exists.
 */
class ProjectStatistics
{
    use ResolvesViewer;

    /**
     * @param  array{projectId: int|string}  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        $project = Project::query()->visibleTo($this->viewer($context))->find($args['projectId']);

        if ($project === null) {
            throw new AuthorizationException(AuthorizationException::MESSAGE);
        }

        return app(Statistics::class)->for($project);
    }
}
