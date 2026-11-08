<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AppSettings;
use App\Support\StrongAuthentication;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects the Fortify and passkey routes that change how an account is
 * secured. Without this, a password alone would be enough to add one's own
 * authenticator app or passkey to an account that already has a passkey,
 * or to change its e-mail or password.
 *
 * - Setup routes: allowed while the user has no factor yet (first setup
 *   after the invitation) or in a strongly authenticated session.
 * - Change routes: only in a strongly authenticated session.
 * - Both open while an admin has switched two-factor authentication off.
 */
class ProtectAccountSecurityRoutes
{
    /** @var list<string> */
    public const SETUP_ROUTES = [
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
        'passkey.registration-options',
        'passkey.store',
    ];

    /** @var list<string> */
    public const STRONG_ROUTES = [
        'passkey.destroy',
        'user-password.update',
        'user-profile-information.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $user = $request->user();

        if ($route === null || ! $user instanceof User || StrongAuthentication::isStrong($request) || ! AppSettings::twoFactorEnabled()) {
            return $next($request);
        }

        $isSetup = $route->named(self::SETUP_ROUTES);

        if (! $isSetup && ! $route->named(self::STRONG_ROUTES)) {
            return $next($request);
        }

        if ($isSetup && StrongAuthentication::needsSetup($user)) {
            return $next($request);
        }

        return response()->json([
            'message' => __('graphql.strong_authentication_required'),
            'code' => EnsureStrongAuthentication::CODE_STRONG_AUTH_REQUIRED,
        ], Response::HTTP_FORBIDDEN);
    }
}
