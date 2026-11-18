<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($args): User {
            $user = User::findOrFail($args['id']);
            $user->delete();

            return $user;
        });
    }
}
