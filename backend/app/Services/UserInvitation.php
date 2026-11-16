<?php

namespace App\Services;

use App\Enums\Locale;
use App\Enums\UserActivity;
use App\Models\Role;
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
    public function invite(string $name, string $email, ?Role $role, Locale $locale, ?UserActivity $activity = null): User
    {
        return DB::transaction(function () use ($name, $email, $role, $locale, $activity): User {
            $user = $this->create($name, $email, $role, Str::password(64), $activity);

            $token = Password::broker('invitations')->createToken($user);
            $user->notify((new UserInvited($token))->locale($locale->value));

            return $user;
        });
    }

    /**
     * Create the user with a password chosen right away (no mail). Created by
     * an admin, so the account is verified and approved at once.
     */
    public function create(string $name, string $email, ?Role $role, string $password, ?UserActivity $activity = null): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'activity' => $activity,
            'password' => $password,
        ]);

        // An admin created the account: no registration checks apply.
        $user->forceFill(['email_verified_at' => now(), 'approved_at' => now()])->save();

        return $user;
    }
}
