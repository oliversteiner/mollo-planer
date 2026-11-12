<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AccountReadiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * First gate of the GraphQL API: a logged-in account whose mail address is not
 * confirmed gets nothing, not even the Viewer query, and is not asked for a
 * second factor yet. Runs before EnsureStrongAuthentication. Guests pass.
 */
class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        return AccountReadiness::locked(AccountReadiness::REASON_EMAIL);
    }
}
