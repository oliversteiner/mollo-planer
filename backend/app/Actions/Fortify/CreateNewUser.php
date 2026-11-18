<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Self-registration (Fortify /register). The account gets the role member
 * and waits for an admin to approve it; it stays locked until the mail
 * address is confirmed, a second factor is set up and the approval is given (EnsureEmailVerified, EnsureApproved).
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input = $this->validate($input);

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }

    /**
     * The rules of the registration. Whether the address is taken is not a rule: that would tell
     * the form which addresses have an account (RegisterController answers both cases alike, the
     * unique index of the database is the last safeguard).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function validate(array $input): array
    {
        return Validator::make($input, [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => $this->passwordRules(),
        ])->validate();
    }
}
