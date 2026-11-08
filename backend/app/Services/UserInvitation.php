<?php

namespace App\Services;

use App\Enums\Locale;
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
    public function invite(string $name, string $email, bool $isAdmin, Locale $locale): User
    {
        return DB::transaction(function () use ($name, $email, $isAdmin, $locale): User {
            $user = $this->create($name, $email, $isAdmin, Str::password(64));

            $token = Password::broker('invitations')->createToken($user);
            $user->notify((new UserInvited($token))->locale($locale->value));

            return $user;
        });
    }

    /**
     * Create the user with a password chosen right away (no mail).
     */
    public function create(string $name, string $email, bool $isAdmin, string $password): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'is_admin' => $isAdmin,
            'password' => $password,
        ]);
    }
}
