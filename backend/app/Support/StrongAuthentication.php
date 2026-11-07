<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

/**
 * Marks a session as strongly authenticated: password plus confirmed 2FA,
 * or a passkey with user verification. The GraphQL API stays locked until
 * the mark is set (see EnsureStrongAuthentication).
 *
 * The mark stores the user id, not just "yes": some events fire before the
 * login completes (passkey) or may not lead to a login at all, so a mark
 * only counts for the user it was set for.
 *
 * Set by App\Listeners\MarkSessionAsStrong, removed by
 * App\Listeners\RevokeStrongSessionWithoutFactor and by logout (the
 * session is invalidated). Login regenerates the session id but keeps
 * its data, so the mark survives the login itself.
 */
final class StrongAuthentication
{
    public const SESSION_KEY = 'auth.strong_user_id';

    public static function mark(Session $session, User $user): void
    {
        $session->put(self::SESSION_KEY, (string) $user->getKey());
    }

    public static function forget(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }

    public static function isStrong(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User
            && $request->hasSession()
            && $request->session()->get(self::SESSION_KEY) === (string) $user->getKey();
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
