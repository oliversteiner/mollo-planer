<?php

namespace App\Core\Identity\Http\Middleware;

use App\Models\User;
use App\Support\AppSettings;
use App\Support\StrongAuthentication;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Same lock as the GraphQL route (EnsureStrongAuthentication), for the page-based authorization flow: a session that
 * lacks the second factor does not authorize anything. The browser is sent to the login of the frontend, which comes
 * back to the authorization request afterwards (parameter redirect); an agent account never gets here.
 */
class EnsureStrongAuthenticationForOAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || StrongAuthentication::isStrong($request) || ! AppSettings::twoFactorEnabled()) {
            return $next($request);
        }

        return redirect()->away(self::loginUrl($request));
    }

    public static function loginUrl(Request $request): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/login?'.http_build_query(['redirect' => $request->fullUrl()]);
    }
}
