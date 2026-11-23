<?php

namespace App\Core\Identity\Oidc;

use Illuminate\Database\Eloquent\Model;

/** The claims about a person, by scope: profile gives the name, email gives the address (same for id_token and userinfo). */
final class UserClaims
{
    /**
     * @param  list<string>  $scopes
     * @return array<string, mixed>
     */
    public static function for(Model $user, array $scopes): array
    {
        $claims = [];

        if (in_array('profile', $scopes, true)) {
            $claims['name'] = $user->getAttribute('name');
        }

        if (in_array('email', $scopes, true)) {
            $claims['email'] = $user->getAttribute('email');
            $claims['email_verified'] = $user->getAttribute('email_verified_at') !== null;
        }

        return $claims;
    }
}
