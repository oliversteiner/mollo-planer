<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AccountReadiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Last gate of the GraphQL API: an account with a confirmed mail address and a
 * second factor, but no approval from an admin yet, sees only the Viewer query.
 * Runs after EnsureStrongAuthentication, so the order is mail, second factor,
 * approval. Guests and approved accounts pass.
 */
class EnsureApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isApproved() || AccountReadiness::isOpenOperation($request)) {
            return $next($request);
        }

        return AccountReadiness::locked(AccountReadiness::REASON_APPROVAL);
    }
}
