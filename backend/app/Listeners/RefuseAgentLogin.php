<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * No browser login for an agent account, however it was tried: password, passkey, "remember me". Both login paths
 * end in the session guard's login(), which fires this event; throwing here ends the attempt before a session is
 * kept. The answer is the one of a wrong password, so it reveals nothing about the account.
 */
class RefuseAgentLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User && $event->user->isAgent()) {
            Auth::guard($event->guard)->logout();

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
    }
}
