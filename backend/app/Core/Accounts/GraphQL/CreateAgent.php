<?php

namespace App\Core\Accounts\GraphQL;

use App\Core\Accounts\AgentAccounts;
use App\GraphQL\Concerns\ConfirmsPassword;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\User;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * An admin creates an agent account in the app. Right (@can manageAgentAccess), strong session (the GraphQL route),
 * recent password confirmation (here), as for its accesses (createAgentToken).
 */
class CreateAgent
{
    use ConfirmsPassword;
    use ResolvesViewer;

    /**
     * @param  array{input: array{name: string}}  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): User
    {
        $admin = $this->viewer($context);
        $this->requireRecentPasswordConfirmation();

        return app(AgentAccounts::class)->create($args['input']['name'], null, $admin);
    }
}
