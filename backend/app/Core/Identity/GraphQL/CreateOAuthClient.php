<?php

namespace App\Core\Identity\GraphQL;

use App\Core\Identity\OAuthClients;
use App\GraphQL\Concerns\ConfirmsPassword;
use App\GraphQL\Concerns\ResolvesViewer;
use Laravel\Passport\Client;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/** An admin registers an application. Right (@can), strong session (the GraphQL route), recent password confirmation (here). */
class CreateOAuthClient
{
    use ConfirmsPassword;
    use ResolvesViewer;

    /**
     * @param  array{name: string, redirectUris: list<string>, confidential: bool}  $args
     * @return array{client: Client, secret: string|null}
     */
    public function __invoke(mixed $root, array $args, GraphQLContext $context): array
    {
        $admin = $this->viewer($context);
        $this->requireRecentPasswordConfirmation();

        $client = app(OAuthClients::class)->create($admin, $args['name'], $args['redirectUris'], $args['confidential']);

        return ['client' => $client, 'secret' => $client->confidential() ? $client->plainSecret : null];
    }
}
