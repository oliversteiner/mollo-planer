<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

/**
 * "Forgot password" answers the same for every address: an unknown address, the broker's
 * 60 second throttle and a sent mail all give 200 and the same text, so nobody can use the
 * form to find out which accounts exist. Only a formally invalid address is refused (422, by
 * the request validation) and the IP throttle answers 429 (see FortifyServiceProvider).
 */
class NeutralPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse
{
    public function __construct(protected string $status) {}

    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(['message' => trans('passwords.sent')], 200);
    }
}
