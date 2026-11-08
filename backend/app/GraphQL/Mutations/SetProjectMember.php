<?php

namespace App\GraphQL\Mutations;

use App\Enums\ProjectRole;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Adds a person to a project or changes their role.
 */
class SetProjectMember
{
    use ResolvesViewer;

    public function __construct(private readonly ProjectMembership $membership) {}

    /**
     * @param  array{projectId: int|string, userId: int|string, role: string}  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): Project
    {
        $viewer = $this->viewer($context);

        return DB::transaction(function () use ($args, $viewer): Project {
            $project = Project::findOrFail($args['projectId']);
            $user = User::find($args['userId'])
                ?? throw ValidationException::withMessages(['userId' => __('validation.exists', ['attribute' => 'userId'])]);

            $this->membership->set($viewer, $project, $user, ProjectRole::from($args['role']));

            return $project->unsetRelation('members');
        });
    }
}
