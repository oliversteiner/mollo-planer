<?php

namespace App\Core\Identity\GraphQL;

use App\Core\Identity\OAuthClients;
use Laravel\Passport\Client;

/** OAuthClient.selfRegistered: no admin registered it (it registered itself, nobody at Mollo has looked at it). */
class SelfRegistered
{
    public function __invoke(Client $client): bool
    {
        return OAuthClients::isSelfRegistered($client);
    }
}
