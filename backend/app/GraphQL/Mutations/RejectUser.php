<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a waiting, self-registered account (admins only, checked by @can).
 */
class RejectUser
{
    /**
     * @param  array{id: int|string}  $args
     */
    public function __invoke(mixed $root, array $args): bool
    {
        return DB::transaction(function () use ($args): bool {
            User::findOrFail($args['id'])->delete();

            return true;
        });
    }
}
