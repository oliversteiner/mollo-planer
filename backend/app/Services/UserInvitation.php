<?php

namespace App\Services;

use App\Enums\Locale;
use App\Enums\UserFunction;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Creates users without a known password and invites them by mail to set
 * their own (link valid 7 days, broker "invitations"). Used by the
 * createUser mutation and by app:create-admin.
 */
class UserInvitation
{
    /**
     * Create the user with a random password nobody knows and send the
     * invitation in the given language.
     */
    public function invite(string $name, string $email, UserRole $role, Locale $locale, ?UserFunction $jobFunction = null): User
    {
        return DB::transaction(function () use ($name, $email, $role, $locale, $jobFunction): User {
            $user = $this->create($name, $email, $role, Str::password(64), $jobFunction);

            $token = Password::broker('invitations')->createToken($user);
            $user->notify((new UserInvited($token))->locale($locale->value));

            return $user;
        });
    }

    /**
     * Create the user with a password chosen right away (no mail).
     */
    public function create(string $name, string $email, UserRole $role, string $password, ?UserFunction $jobFunction = null): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'job_function' => $jobFunction,
            'password' => $password,
        ]);
    }
}
