<?php

namespace App\GraphQL\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Asks for a recent password confirmation, the one Fortify keeps in the session (POST /user/confirm-password,
 * also set by the login with a password). It counts for auth.password_timeout seconds. The frontend confirms
 * first (usePasswordPrompt); this is the check on the server.
 */
trait ConfirmsPassword
{
    /**
     * @throws ValidationException
     */
    protected function requireRecentPasswordConfirmation(): void
    {
        $confirmedAt = (int) session('auth.password_confirmed_at', 0);

        if (time() - $confirmedAt > (int) config('auth.password_timeout')) {
            throw ValidationException::withMessages(['password' => __('graphql.password_confirmation_required')]);
        }
    }
}
