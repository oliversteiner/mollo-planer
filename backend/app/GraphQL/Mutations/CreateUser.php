<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Creates a user (admins only, checked by @can). The user gets a random
 * password nobody knows and an invitation mail with a link to set their
 * own password (valid 7 days), in the admin's current language.
 */
class CreateUser
{
    /**
     * @param  array{name: string, email: string, is_admin?: bool}  $args
     */
    public function __invoke(mixed $root, array $args): User
    {
        $user = User::create([
            'name' => $args['name'],
            'email' => $args['email'],
            'is_admin' => $args['is_admin'] ?? false,
            'password' => Str::password(64),
        ]);

        $token = Password::broker('invitations')->createToken($user);
        $user->notify((new UserInvited($token))->locale(app()->getLocale()));

        return $user;
    }
}
