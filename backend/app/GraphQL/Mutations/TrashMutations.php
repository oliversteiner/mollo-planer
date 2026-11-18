<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Services\Deletion;
use App\Services\TrashListing;
use Illuminate\Support\Facades\Gate;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * restoreFromTrash, purgeFromTrash, emptyProjectTrash (docs/auftrag-m2.md §3).
 * A deletion is no model of its own, so the policy of the element it started
 * with decides (restore, forceDelete); emptyProjectTrash is checked by @can.
 */
class TrashMutations
{
    use ResolvesViewer;

    public function __construct(
        private readonly Deletion $deletion,
        private readonly TrashListing $listing,
    ) {}

    /**
     * Returns the entry as it was in the trash.
     *
     * @param  array{deletionId: string}  $args
     * @return array<string, mixed>
     */
    public function restore(mixed $root, array $args, GraphQLContext $context): array
    {
        $viewer = $this->viewer($context);
        Gate::forUser($viewer)->authorize('restore', $this->deletion->topOf($args['deletionId']));

        $item = $this->listing->ofDeletion($args['deletionId'], $viewer);
        $this->deletion->restore($args['deletionId'], $viewer);

        return $item;
    }

    /**
     * @param  array{deletionId: string}  $args
     */
    public function purge(mixed $root, array $args, GraphQLContext $context): bool
    {
        Gate::forUser($this->viewer($context))->authorize('forceDelete', $this->deletion->topOf($args['deletionId']));

        $this->deletion->purge($args['deletionId']);

        return true;
    }

    /**
     * @param  array{projectId: string}  $args
     */
    public function emptyProjectTrash(mixed $root, array $args): int
    {
        return $this->deletion->emptyProjectTrash(Project::query()->findOrFail($args['projectId']));
    }
}
