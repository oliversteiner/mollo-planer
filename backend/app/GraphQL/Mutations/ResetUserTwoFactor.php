<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Removes the authenticator app and all passkeys of a user (lost device)
 * and ends their sessions, so the next login sets up a new factor.
 */
class ResetUserTwoFactor
{
    /**
     * @param  array{id: int|string}  $args
     */
    public function __invoke(mixed $root, array $args): User
    {
        $user = User::findOrFail($args['id']);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $user->passkeys()->delete();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }

        return $user;
    }
}
