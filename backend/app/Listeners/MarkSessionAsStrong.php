<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\StrongAuthentication;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Events\PasskeyVerified;

/**
 * Sets the "strongly authenticated" mark when a second factor was used:
 * - a valid 2FA code or recovery code in the login challenge,
 * - a passkey verified with user verification (login or confirmation).
 *
 * Setting up a factor marks the session only if it is the user's first
 * factor (first setup after the invitation). Adding a factor to an account
 * that already has one proves nothing about the existing factor;
 * ProtectAccountSecurityRoutes blocks that anyway unless the session is
 * already strong.
 */
class MarkSessionAsStrong
{
    public function handle(
        ValidTwoFactorAuthenticationCodeProvided|TwoFactorAuthenticationConfirmed|PasskeyVerified|PasskeyRegistered $event,
    ): void {
        $user = $event->user;

        if (! $user instanceof User || ! app()->bound('session.store')) {
            return;
        }

        $isFirstFactor = match (true) {
            $event instanceof TwoFactorAuthenticationConfirmed => ! $user->hasPasskeysEnabled(),
            $event instanceof PasskeyRegistered => $user->two_factor_confirmed_at === null && $user->passkeys()->count() === 1,
            default => null,
        };

        if ($isFirstFactor === false) {
            return;
        }

        StrongAuthentication::mark(session()->driver(), $user);
    }
}
