<?php

namespace App\GraphQL\Concerns;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

trait ResolvesViewer
{
    /**
     * The logged-in user. Query and Mutation are behind @guard, so a
     * missing user is an error, not a case to handle.
     *
     * @throws AuthenticationException
     */
    protected function viewer(GraphQLContext $context): User
    {
        $user = $context->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
