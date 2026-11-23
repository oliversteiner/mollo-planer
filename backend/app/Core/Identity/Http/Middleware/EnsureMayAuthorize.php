<?php

namespace App\Core\Identity\Http\Middleware;

use App\Core\Identity\AuthorizationEligibility;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the account before it authorizes anything (AuthorizationEligibility). At the authorization page a person who may not is
 * sent to the page of the frontend with the reason (they navigate there, so there is no JSON); on the JSON routes of that page
 * the answer is 403 with the reason, which the page shows. A guest goes on: login is the business of the authorization itself.
 */
class EnsureMayAuthorize
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $reason = AuthorizationEligibility::problem($user, AuthorizationEligibility::requestedScopes($request));

        if ($reason === null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'account_not_allowed', 'reason' => $reason], Response::HTTP_FORBIDDEN);
        }

        return redirect()->away(rtrim((string) config('app.frontend_url'), '/').'/oauth/consent?'.http_build_query(['error' => $reason]));
    }
}
