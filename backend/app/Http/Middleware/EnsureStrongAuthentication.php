<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\StrongAuthentication;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks the GraphQL route for logged-in users whose session is not strongly
 * authenticated. Guests pass through; @guard rejects them per field.
 *
 * The response is a GraphQL error with extensions.code:
 * - SECOND_FACTOR_SETUP_REQUIRED: no 2FA app and no passkey yet, the
 *   frontend leads to the setup.
 * - STRONG_AUTHENTICATION_REQUIRED: a factor exists, but this session did
 *   not use it; the frontend logs in again.
 */
class EnsureStrongAuthentication
{
    public const CODE_SETUP_REQUIRED = 'SECOND_FACTOR_SETUP_REQUIRED';

    public const CODE_STRONG_AUTH_REQUIRED = 'STRONG_AUTHENTICATION_REQUIRED';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || StrongAuthentication::isStrong($request)) {
            return $next($request);
        }

        $code = StrongAuthentication::needsSetup($user)
            ? self::CODE_SETUP_REQUIRED
            : self::CODE_STRONG_AUTH_REQUIRED;

        return response()->json([
            'errors' => [[
                'message' => __('graphql.'.strtolower($code)),
                'extensions' => ['code' => $code],
            ]],
        ], Response::HTTP_FORBIDDEN);
    }
}
