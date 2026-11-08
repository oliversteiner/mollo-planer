<?php

namespace App\GraphQL\Resolvers;

use App\GraphQL\Concerns\ResolvesViewer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Passkeys\Passkey;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

/**
 * User.twoFactorConfirmed, hasPasskey and passkeys: how an account is
 * secured. The person and admins see the flags (UserPolicy::viewSecurity),
 * only the person sees the list of passkeys; for everyone else null.
 */
class UserSecurity
{
    use ResolvesViewer;

    /**
     * @param  array<string, mixed>  $args
     */
    public function twoFactorConfirmed(User $user, array $args, GraphQLContext $context): ?bool
    {
        return $this->viewer($context)->can('viewSecurity', $user)
            ? $user->two_factor_confirmed_at !== null
            : null;
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function hasPasskey(User $user, array $args, GraphQLContext $context): ?bool
    {
        return $this->viewer($context)->can('viewSecurity', $user)
            ? $user->hasPasskeysEnabled()
            : null;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return Collection<int, Passkey>|null
     */
    public function passkeys(User $user, array $args, GraphQLContext $context): ?Collection
    {
        return $this->viewer($context)->is($user)
            ? $user->passkeys()->latest('id')->get()
            : null;
    }
}
