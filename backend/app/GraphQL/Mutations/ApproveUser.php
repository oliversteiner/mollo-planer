<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($args): User {
            $user = User::findOrFail($args['id']);
            $user->forceFill(['approved_at' => now()])->save();

            return $user;
        });
    }
}
