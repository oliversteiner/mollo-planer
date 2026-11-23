<?php

namespace App\Core\Identity;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * A person as the holder of an OAuth token: the table of the users, nothing else. The user model of the planer
 * uses the token trait of Sanctum, which cannot sit in one class with the one of Passport; the guard "oauth"
 * uses this model, so the base does not depend on the planer's user model.
 */
class OAuthUser extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
}
