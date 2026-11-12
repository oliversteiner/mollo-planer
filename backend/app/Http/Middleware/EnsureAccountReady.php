<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks the GraphQL API for self-registered accounts until the mail address
 * is confirmed and an admin has approved the account. Only the Viewer query
 * passes, so the frontend can show why the account is locked. Guests and
 * accounts that are ready pass through.
 *
 * Runs after EnsureStrongAuthentication. The response is a GraphQL error with
 * extensions.code ACCOUNT_NOT_READY and extensions.reason email|approval.
 */
class EnsureAccountReady
{
    public const CODE = 'ACCOUNT_NOT_READY';

    public const REASON_EMAIL = 'email';

    public const REASON_APPROVAL = 'approval';

    /** Operation names that stay open while the account is locked (frontend query names). */
    public const OPEN_OPERATIONS = ['Viewer'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ($user->hasVerifiedEmail() && $user->isApproved())) {
            return $next($request);
        }

        if (in_array($request->input('operationName'), self::OPEN_OPERATIONS, true)) {
            return $next($request);
        }

        $reason = $user->hasVerifiedEmail() ? self::REASON_APPROVAL : self::REASON_EMAIL;

        return response()->json([
            'errors' => [[
                'message' => __('graphql.account_not_ready_'.$reason),
                'extensions' => ['code' => self::CODE, 'reason' => $reason],
            ]],
        ], Response::HTTP_FORBIDDEN);
    }
}
