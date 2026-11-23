<?php

namespace App\Core\Identity\GraphQL;

use App\Core\Identity\OAuthClients;
use App\GraphQL\Concerns\ConfirmsPassword;
use App\GraphQL\Concerns\ResolvesViewer;
use Laravel\Passport\Client;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/** An admin revokes an application and all its tokens. Same conditions as CreateOAuthClient. */
class RevokeOAuthClient
{
    use ConfirmsPassword;
    use ResolvesViewer;

    /** @param  array{id: string|int}  $args */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): ?Client
    {
        $admin = $this->viewer($context);
        $this->requireRecentPasswordConfirmation();

        return app(OAuthClients::class)->revoke($admin, (string) $args['id']);
    }
}
