<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * User.assignedTasks: tasks assigned to this user that the viewer may see.
 */
class UserTasks
{
    use ResolvesViewer;

    /**
     * @param  array<string, mixed>  $args
     * @return Collection<int, Task>
     */
    public function __invoke(User $user, array $args, GraphQLContext $context): Collection
    {
        return Task::query()
            ->assignedTo($user)
            ->visibleTo($this->viewer($context))
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }
}
