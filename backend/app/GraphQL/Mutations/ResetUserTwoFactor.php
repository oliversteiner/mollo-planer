<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use App\Services\UserSecurity;

/**
 * Removes the authenticator app and all passkeys of a user (lost device),
 * ends their sessions, "remember me" logins and Claude access tokens, so
 * the next login sets up a new factor. The steps are in UserSecurity, the
 * artisan command app:user-2fa uses them too.
 */
class ResetUserTwoFactor
{
    /**
     * @param  array{id: int|string}  $args
     */
    public function __invoke(mixed $root, array $args): User
    {
        $user = User::findOrFail($args['id']);

        app(UserSecurity::class)->resetTwoFactor($user);

        return $user;
    }
}
