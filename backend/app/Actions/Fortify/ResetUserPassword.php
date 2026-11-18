<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\UserSecurity;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        // Whoever knew the old password may be signed in somewhere: end sessions, "remember me" logins and
        // the access tokens for Claude. The reset by mail logs nobody in, and the second factor stays.
        app(UserSecurity::class)->revokeAccess($user);
    }
}
