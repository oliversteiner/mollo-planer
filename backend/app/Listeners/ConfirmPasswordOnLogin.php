<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

/**
 * Signing in with the password counts as having confirmed the password (docs: Auftrag 22 O6): the setup of the
 * first factor right after the login does not ask for it again. Fortify keeps the time of the confirmation in the
 * session (what POST /user/confirm-password sets, and GET /user/confirmed-password-status reads); the window is
 * auth.password_timeout (3 hours), after it the password is asked for again as before.
 *
 * Set only when the login is complete and a password was typed in it: the login itself (an account without a
 * factor) or the end of the challenge (the password was entered in the step before, the code only ends the
 * login). A passkey login (/passkeys/login) types no password and does not count. The protection of the
 * account routes by a strong session (ProtectAccountSecurityRoutes) is a separate rule and stays.
 */
class ConfirmPasswordOnLogin
{
    public function handle(Login $event): void
    {
        if (! app()->bound('session.store') || ! request()->is('login', 'two-factor-challenge')) {
            return;
        }

        session()->put('auth.password_confirmed_at', time());
    }
}
