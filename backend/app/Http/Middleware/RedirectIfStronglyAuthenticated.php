<?php

namespace App\Http\Middleware;

use App\Support\AppSettings;
use App\Support\StrongAuthentication;
use Closure;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The "guest" middleware of the login routes (password, 2FA, passkey,
 * invitation), replacing Laravel's RedirectIfAuthenticated.
 *
 * A session that is logged in but not strongly authenticated (2FA on,
 * second factor missing in this session) is let through: the API refuses
 * it with STRONG_AUTHENTICATION_REQUIRED and the app sends it to the login,
 * so the login must accept it, or nobody gets out of that state.
 *
 * A fully logged-in session gets a JSON answer instead of a redirect to "/"
 * (which the API does not have), so the SPA simply goes on with it.
 */
class RedirectIfStronglyAuthenticated extends RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        foreach (empty($guards) ? [null] : $guards as $guard) {
            if (! Auth::guard($guard)->check()) {
                continue;
            }

            if (AppSettings::twoFactorEnabled() && ! StrongAuthentication::isStrong($request)) {
                return $next($request);
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Already authenticated.', 'two_factor' => false]);
            }

            return redirect($this->redirectTo($request));
        }

        return $next($request);
    }
}
