<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * User.projects: the projects of this user that the viewer may see.
 */
class UserProjects
{
    use ResolvesViewer;

    /**
     * @param  array<string, mixed>  $args
     * @return Collection<int, Project>
     */
    public function __invoke(User $user, array $args, GraphQLContext $context): Collection
    {
        return $user->projects()->visibleTo($this->viewer($context))->orderBy('projects.name')->get();
    }
}
