<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

/**
 * Marks a session as strongly authenticated: password plus confirmed 2FA,
 * or a passkey login with user verification. The GraphQL API stays locked
 * until the mark is set (see EnsureStrongAuthentication).
 *
 * The login flows set the mark (step 9). Session regeneration on login keeps
 * the data, logout invalidates the session and with it the mark.
 */
final class StrongAuthentication
{
    public const SESSION_KEY = 'auth.strong';

    public static function mark(Session $session): void
    {
        $session->put(self::SESSION_KEY, true);
    }

    public static function isStrong(Request $request): bool
    {
        return $request->hasSession()
            && $request->session()->get(self::SESSION_KEY) === true;
    }

    /**
     * True if the user has neither a confirmed authenticator app nor a
     * passkey, so the frontend must lead them to the setup first.
     */
    public static function needsSetup(User $user): bool
    {
        return $user->two_factor_confirmed_at === null
            && ! $user->hasPasskeysEnabled();
    }
}
