<?php

namespace App\GraphQL\Queries;

use App\Enums\Right;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Services\TrashListing;
use App\Support\Rights;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Query.trash: with projectId the trash of that project (for people with
 * trash.restore or trash.purge there, otherwise empty), without it the
 * deleted projects the viewer may bring back.
 */
class Trash
{
    use ResolvesViewer;

    public function __construct(private readonly TrashListing $listing) {}

    /**
     * @param  array{projectId?: string|null}  $args
     * @return list<array<string, mixed>>
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        $viewer = $this->viewer($context);

        if (($args['projectId'] ?? null) === null) {
            return $this->listing->deletedProjects($viewer);
        }

        $project = Project::query()->visibleTo($viewer)->find($args['projectId']);

        if ($project === null || ! (Rights::allows($viewer, Right::TRASH_RESTORE, $project) || Rights::allows($viewer, Right::TRASH_PURGE, $project))) {
            return [];
        }

        return $this->listing->inProject($project, $viewer);
    }
}
