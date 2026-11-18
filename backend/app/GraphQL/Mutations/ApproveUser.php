<?php

namespace App\GraphQL\Mutations;

use App\Actions\ReviewRegistration;
use App\Models\User;

/**
 * Releases a self-registered account (admins only, checked by @can). The
 * person can use the app after the next request.
 */
class ApproveUser
{
    /**
     * @param  array{id: int|string}  $args
     */
    public function __invoke(mixed $root, array $args): User
    {
        return app(ReviewRegistration::class)->approve(User::findOrFail($args['id']));
    }
}
