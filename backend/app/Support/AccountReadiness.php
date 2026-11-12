<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared answer of the two account gates (EnsureEmailVerified, EnsureApproved).
 * A locked account gets a GraphQL error with extensions.code ACCOUNT_NOT_READY
 * and extensions.reason email (mail not confirmed) or approval (waiting for an
 * admin). The frontend shows the matching page.
 */
final class AccountReadiness
{
    public const CODE = 'ACCOUNT_NOT_READY';

    public const REASON_EMAIL = 'email';

    public const REASON_APPROVAL = 'approval';

    /** Operation names that stay open for an account waiting for approval (frontend query names). */
    public const OPEN_OPERATIONS = ['Viewer'];

    public static function isOpenOperation(Request $request): bool
    {
        return in_array($request->input('operationName'), self::OPEN_OPERATIONS, true);
    }

    public static function locked(string $reason): JsonResponse
    {
        return response()->json([
            'errors' => [[
                'message' => __('graphql.account_not_ready_'.$reason),
                'extensions' => ['code' => self::CODE, 'reason' => $reason],
            ]],
        ], Response::HTTP_FORBIDDEN);
    }
}
