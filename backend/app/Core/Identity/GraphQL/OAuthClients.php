<?php

namespace App\Core\Identity\GraphQL;

use Illuminate\Database\Eloquent\Collection;
use Laravel\Passport\Client;

/** All clients of the login service, newest first (the right is checked by @can in the schema). */
class OAuthClients
{
    /** @return Collection<int, Client> */
    public function __invoke(): Collection
    {
        return Client::query()->orderByDesc('created_at')->orderBy('name')->get();
    }
}
