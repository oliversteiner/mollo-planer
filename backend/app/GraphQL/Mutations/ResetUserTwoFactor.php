<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use App\Support\McpTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Removes the authenticator app and all passkeys of a user (lost device),
 * ends their sessions, "remember me" logins and Claude access tokens, so
 * the next login sets up a new factor.
 */
class ResetUserTwoFactor
{
    /**
     * @param  array{id: int|string}  $args
     */
    public function __invoke(mixed $root, array $args): User
    {
        return DB::transaction(function () use ($args): User {
            $user = User::findOrFail($args['id']);

            // A new remember token also signs out "remember me" cookies,
            // for example on the lost device.
            $user->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => Str::random(60),
            ])->save();

            $user->passkeys()->delete();
            // Also the access tokens for Claude, for example on a lost laptop.
            McpTokens::revokeAll($user);

            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table', 'sessions'))
                    ->where('user_id', $user->getKey())
                    ->delete();
            }

            return $user;
        });
    }
}
