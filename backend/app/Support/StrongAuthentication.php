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

    /** How the person proved themselves (OpenID Connect amr: pwd, otp, hwk) and when, for the id_token. */
    public const SESSION_METHODS = 'auth.methods';

    public const SESSION_TIME = 'auth.time';

    /** @param  list<string>  $methods  amr values of the factor that was just used */
    public static function mark(Session $session, User $user, array $methods = []): void
    {
        $session->put(self::SESSION_KEY, (string) $user->getKey());
        $session->put(self::SESSION_METHODS, array_values(array_unique($methods)));
        $session->put(self::SESSION_TIME, time());
    }

    public static function forget(Session $session): void
    {
        $session->forget([self::SESSION_KEY, self::SESSION_METHODS, self::SESSION_TIME]);
    }

    /** @return list<string> amr values of this session; "mfa" when only the mark is known */
    public static function methods(Request $request): array
    {
        $methods = $request->hasSession() ? $request->session()->get(self::SESSION_METHODS) : null;

        return is_array($methods) && $methods !== [] ? array_values($methods) : ['mfa'];
    }

    public static function authenticatedAt(Request $request): int
    {
        return (int) ($request->hasSession() ? $request->session()->get(self::SESSION_TIME, time()) : time());
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
