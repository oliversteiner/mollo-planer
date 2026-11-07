<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\StrongAuthentication;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Passkeys\Events\PasskeyDeleted;

/**
 * Removes the mark when the user just removed their last factor, so the
 * API locks again and the frontend leads to the setup.
 */
class RevokeStrongSessionWithoutFactor
{
    public function handle(TwoFactorAuthenticationDisabled|PasskeyDeleted $event): void
    {
        $user = $event->user;

        if ($user instanceof User && app()->bound('session.store') && StrongAuthentication::needsSetup($user->fresh() ?? $user)) {
            StrongAuthentication::forget(session()->driver());
        }
    }
}
