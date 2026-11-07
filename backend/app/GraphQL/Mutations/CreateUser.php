<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates a user (admins only, checked by @can). The user gets a random
 * password nobody knows; the invitation mail with a link to set their own
 * password is added in step 9.
 */
class CreateUser
{
    /**
     * @param  array{name: string, email: string, is_admin?: bool}  $args
     */
    public function __invoke(mixed $root, array $args): User
    {
        return User::create([
            'name' => $args['name'],
            'email' => $args['email'],
            'is_admin' => $args['is_admin'] ?? false,
            'password' => Str::password(64),
        ]);
    }
}
