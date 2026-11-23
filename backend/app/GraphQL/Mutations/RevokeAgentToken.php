<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ConfirmsPassword;
use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\User;
use App\Services\AgentAccess;
use Laravel\Sanctum\PersonalAccessToken;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * An admin revokes one connector access of an agent account. An access of somebody else is not found (null).
 */
class RevokeAgentToken
{
    use ConfirmsPassword;
    use ResolvesViewer;

    /**
     * @param  array{userId: int|string, tokenId: int|string}  $args
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): ?PersonalAccessToken
    {
        $admin = $this->viewer($context);
        $this->requireRecentPasswordConfirmation();

        return app(AgentAccess::class)->revoke($admin, User::findOrFail($args['userId']), $args['tokenId']);
    }
}
