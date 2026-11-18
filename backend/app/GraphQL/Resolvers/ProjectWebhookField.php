<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\Project;
use App\Models\ProjectWebhook;
use Illuminate\Support\Facades\Gate;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * Project.webhook: only for those who may change the project (project.update), else null.
 * The secret is no field of the type and never leaves the server.
 */
class ProjectWebhookField
{
    use ResolvesViewer;

    public function __invoke(Project $project, array $args, GraphQLContext $context): ?ProjectWebhook
    {
        if (! Gate::forUser($this->viewer($context))->allows('update', $project)) {
            return null;
        }

        return $project->webhook;
    }
}
