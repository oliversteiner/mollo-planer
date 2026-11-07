<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectMembership;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Removes a person from a project and unassigns their tasks there.
 */
class RemoveProjectMember
{
    use ResolvesViewer;

    public function __construct(private readonly ProjectMembership $membership) {}

    /**
     * @param  array{projectId: int|string, userId: int|string}  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): Project
    {
        $project = Project::findOrFail($args['projectId']);
        $user = User::find($args['userId']);

        if ($user !== null) {
            $this->membership->remove($this->viewer($context), $project, $user);
        }

        return $project->unsetRelation('members');
    }
}
