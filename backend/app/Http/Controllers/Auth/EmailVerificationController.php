<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;

/**
 * The link in the confirmation mail works in any browser, also without a session
 * (the mail program often opens it elsewhere than where the person registered).
 *
 * Opening the link confirms nothing: mail scanners and link previews call links
 * in advance. GET only checks the signature and hands over to the frontend page,
 * which shows a button; the person confirms with POST to the same address.
 * Laravel's signature covers path and query, not the method, so the POST passes
 * the same check. Nobody is logged in by either call.
 *
 * Every failure (signature wrong or expired, hash wrong, account gone) looks the
 * same and says nothing about which accounts exist.
 */
class EmailVerificationController extends Controller
{
    /** GET: check the signature only and go on to the frontend page (nothing is read or written). */
    public function show(Request $request, string $id, string $hash): RedirectResponse
    {
        $page = rtrim((string) config('app.frontend_url'), '/').'/verify-email';

        return redirect()->away($request->hasValidSignature()
            ? $page.'?'.http_build_query(['id' => $id, 'hash' => $hash] + Arr::only($request->query(), ['expires', 'signature']))
            : $page.'?status=invalid');
    }

    /** POST: confirm. Always 200 with { status: ok | already | invalid }. */
    public function confirm(Request $request, string $id, string $hash): JsonResponse
    {
        $user = $request->hasValidSignature() && ctype_digit($id) ? User::query()->find((int) $id) : null;

        if (! $user instanceof User || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json(['status' => 'invalid']);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['status' => 'already']);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Logged in: send the mail again. 204 when the address is confirmed meanwhile
     * (the frontend then checks the account again), otherwise 202.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return new JsonResponse('', 204);
        }

        $user->sendEmailVerificationNotification();

        return new JsonResponse('', 202);
    }

    /**
     * Not logged in (the link ran out): send the mail again to an account that waits for confirmation.
     * The answer is always the same, whether the address is known, confirmed or not.
     */
    public function resendToAddress(Request $request): JsonResponse
    {
        $email = trim((string) $request->validate(['email' => ['required', 'email', 'max:255']])['email']);

        User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->whereNull('email_verified_at')
            ->first()
            ?->sendEmailVerificationNotification();

        return new JsonResponse(['message' => __('verification.resent')], 202);
    }
}
