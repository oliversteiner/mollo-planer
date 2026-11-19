<?php

namespace App\GraphQL\Mutations;

use App\Actions\ReviewRegistration;
use App\Models\User;

/**
 * Deletes a waiting, self-registered account (admins only, checked by @can)
 * and returns it as read before, like deleteUser (schema-review C3).
 */
class RejectUser
{
    /**
     * @param  array{id: int|string}  $args
     */
    public function __invoke(mixed $root, array $args): User
    {
        return app(ReviewRegistration::class)->reject(User::findOrFail($args['id']));
    }
}
